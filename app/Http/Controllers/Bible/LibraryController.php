<?php

namespace App\Http\Controllers\Bible;

use App\Http\Controllers\Connections\ArclightController;
use App\Traits\AccessControlAPI;
use App\Traits\CallsBucketsTrait;
use Illuminate\Http\JsonResponse;

use App\Models\Language\Language;
use App\Models\Bible\BibleFileset;
use App\Models\Organization\Asset;

use App\Transformers\V2\LibraryVolumeTransformer;
use App\Transformers\V2\LibraryCatalog\LibraryMetadataTransformer;

use App\Http\Controllers\APIController;

class LibraryController extends APIController
{
    use AccessControlAPI;
    use CallsBucketsTrait;

    /**
     *
     * @link https://api.dbp.test/library/metadata?key=1234&pretty&v=2
     *
     * @OA\Get(
     *     path="/library/metadata",
     *     tags={"Library Catalog"},
     *     summary="This returns copyright and associated organizations info.",
     *     description="",
     *     operationId="v2_library_metadata",
     *     @OA\Parameter(name="dam_id", in="query", description="The DAM ID for which to retrieve library metadata.", @OA\Schema(ref="#/components/schemas/BibleFileset/properties/id")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v2_library_metadata")),
     *         @OA\MediaType(mediaType="application/xml",  @OA\Schema(ref="#/components/schemas/v2_library_metadata")),
     *         @OA\MediaType(mediaType="text/yaml",        @OA\Schema(ref="#/components/schemas/v2_library_metadata")),
     *         @OA\MediaType(mediaType="text/csv",         @OA\Schema(ref="#/components/schemas/v2_library_metadata"))
     *     )
     * )
     *
     *
     * @return mixed
     */
    public function metadata()
    {
        $fileset_id = checkParam('dam_id') ?? false;
        $asset_id  = checkParam('bucket|bucket_id|asset_id') ?? config('filesystems.disks.s3_fcbh.bucket');

        if ($fileset_id) {
            // avoids using filesets with more than 7 characters
            $fileset_id = str_split($fileset_id, 7)[0];
            if (strlen($fileset_id) < 6) {
                return $this
                    ->setStatusCode(404)
                    ->replyWithError(trans('api.bible_fileset_errors_404', ['id' => $fileset_id]));
            }
        }

        $fileset_id_cache = $fileset_id ? $fileset_id : 'allmetadata';
        $cache_params = [
            $fileset_id_cache,
            $asset_id,
        ];

        $metadata = cacheRemember('v2_library_metadata', $cache_params, now()->addDay(), function () use ($fileset_id, $asset_id) {
            $metadata = BibleFileset::where('asset_id', $asset_id)
                ->select(
                    'bible_filesets.id',
                    'bible_filesets.hash_id',
                    'bible_fileset_copyrights.copyright',
                    'bible_fileset_copyrights.copyright_description',
                    'organizations.id as organization_id',
                    'organization_translations.name',
                    'organizations.slug',
                    'organizations.url_website',
                    'organizations.url_donate',
                    'organizations.address',
                    'organizations.address2',
                    'organizations.city',
                    'organizations.state',
                    'organizations.country',
                    'organizations.zip',
                    'organizations.phone',
                    'organization_translations.language_id',
                    'organization_translations.vernacular',
                    'bible_fileset_copyright_roles.name as role_name',
                )
                ->when($fileset_id, function ($q) use ($fileset_id) {
                    $q->where('bible_filesets.id', 'LIKE', "$fileset_id%")
                        ->orWhere('bible_filesets.id', substr($fileset_id, 0, -4))
                        ->orWhere('bible_filesets.id', substr($fileset_id, 0, -2));
                })
                ->join('bible_fileset_copyrights', 'bible_fileset_copyrights.hash_id', 'bible_filesets.hash_id')
                ->join(
                    'bible_fileset_copyright_organizations',
                    'bible_fileset_copyright_organizations.hash_id',
                    'bible_filesets.hash_id'
                )
                ->join(
                    'bible_fileset_copyright_roles',
                    'bible_fileset_copyright_roles.id',
                    'bible_fileset_copyright_organizations.organization_role'
                )
                ->join('organizations', 'organizations.id', 'bible_fileset_copyright_organizations.organization_id')
                ->leftJoin('organization_translations', function ($query) {
                    $query->on('organizations.id', 'organization_translations.organization_id')
                    ->whereRaw(
                        '(organization_translations.vernacular = ? OR organization_translations.language_id = ?)',
                        [1, $GLOBALS['i18n_id']]
                    )
                    ;
                })
                ->has('copyright')
                ->get();

            $metadata_processed = $this->processMetadata($metadata);

            if (!$metadata) {
                return $this
                    ->setStatusCode(404)
                    ->replyWithError(trans('api.bible_fileset_errors_404', ['id' => $fileset_id]));
            }

            return fractal($metadata_processed, new LibraryMetadataTransformer())->serializeWith($this->serializer);
        });

        return $this->reply($metadata);
    }

    /**
     * Process the metadata to keep an only fileset record. It will use the hash_id to
     * identy the fileset record.
     *
     * @return array
     */
    private function processMetadata($metadata) : array
    {
        $metadata_processed = [];

        foreach ($metadata as $fileset_fetched) {
            if (!isset($metadata_processed[$fileset_fetched->hash_id])) {
                $metadata_temp = new \stdClass;
            } else {
                $metadata_temp = $metadata_processed[$fileset_fetched->hash_id];
            }

            $metadata_temp->id = $fileset_fetched->id;
            $metadata_temp->copyright = $fileset_fetched->copyright;
            $metadata_temp->copyright_description = $fileset_fetched->copyright_description;

            if (!isset($metadata_temp->organization) || $metadata_temp->organization === false) {
                $metadata_temp->organization = $this->setOrganizationMetadata($fileset_fetched);
            }

            $metadata_processed[$fileset_fetched->hash_id] = $metadata_temp;
        }

        return $metadata_processed;
    }

    /**
     * Get the organization detail for each fileset fetched
     *
     * @return object
     */
    private function setOrganizationMetadata($fileset_fetched)
    {
        if (!isset($fileset_fetched->organization_id)) {
            return false;
        }

        $organization = new \stdClass;
        $organization->id = $fileset_fetched->organization_id ?? null;
        $organization->name = $fileset_fetched->name && (int) $fileset_fetched->vernacular === 1
            ? $fileset_fetched->name
            : '';

        $organization->slug =
            $fileset_fetched->name && $fileset_fetched->language_id === $GLOBALS['i18n_id']
                ? $fileset_fetched->name
                : $fileset_fetched->slug;

        $organization->role_name = $fileset_fetched->role_name ?? '';
        $organization->url_website = $fileset_fetched->url_website ?? '';
        $organization->url_donate = $fileset_fetched->url_donate ?? '';
        $organization->address = $fileset_fetched->address ?? '';
        $organization->address2 = $fileset_fetched->address2 ?? '';
        $organization->city = $fileset_fetched->city ?? '';
        $organization->state = $fileset_fetched->state ?? '';
        $organization->country = $fileset_fetched->country ?? '';
        $organization->zip = $fileset_fetched->zip ?? '';
        $organization->phone = $fileset_fetched->phone ?? '';

        return $organization;
    }

    /**
     *
     * Get the list of versions defined in the system
     *
     * @OA\Get(
     *     path="/library/version",
     *     tags={"Library Catalog"},
     *     summary="Returns Audio File path information",
     *     description="This call returns the file path information for audio files for a volume. This information can
     *     be used with the response of the /audio/location call to create a URI to retrieve the audio files.",
     *     operationId="v2_library_version",
     *     @OA\Parameter(
     *         name="code",
     *         in="query",
     *         @OA\Schema(ref="#/components/schemas/BibleFileset/properties/id"),
     *         description="The abbreviated `BibleFileset` id created from the letters after the iso",
     *         required=true,
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         @OA\Schema(ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *         description="The name of the version in the language that it's written in"
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         @OA\Schema(type="string",title="encoding"),
     *         description="The name of the version in english"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v2_library_version")),
     *         @OA\MediaType(mediaType="application/xml", @OA\Schema(ref="#/components/schemas/v2_library_version")),
     *         @OA\MediaType(mediaType="text/csv", @OA\Schema(ref="#/components/schemas/v2_library_version")),
     *         @OA\MediaType(mediaType="text/x-yaml", @OA\Schema(ref="#/components/schemas/v2_library_version"))
     *     )
     * )
     *
     * @OA\Schema (
     *     type="object",
     *     schema="v2_library_version",
     *     description="The various version ids in the old version 2 style",
     *     title="v2_library_version",
     *     @OA\Xml(name="v2_library_version"),
     *     @OA\Property(
     *         property="id",
     *         type="string",
     *         description="The abbreviated `BibleFileset` id created from letters after the iso code"),
     *     @OA\Property(
     *         property="eng_title",
     *         type="string",
     *         description="The name of the version in the language that it's written in"),
     *     @OA\Property(
     *         property="ver_title",
     *         type="string",
     *         description="The name of the version in english")
     * )
     *
     * @return JsonResponse
     */
    public function version()
    {
        $code = checkParam('code|fileset_id');
        $name = checkParam('name');
        $sort = checkParam('sort_by');

        $cache_params = $this->removeSpaceFromCacheParameters([$code, $name, $sort]);
        $versions = cacheRemember('v2_library_version', $cache_params, now()->addDay(), function () use ($code, $sort, $name) {
            $english_id = Language::where('iso', 'eng')->first()->id ?? '6414';

            $versions = BibleFileset::where('asset_id', config('filesystems.disks.s3_fcbh.bucket'))
                ->rightJoin('bible_fileset_connections as bibles', 'bibles.hash_id', 'bible_filesets.hash_id')
                ->join('bible_translations as ver_title', function ($join) use ($name) {
                    $join->on('ver_title.bible_id', 'bibles.bible_id')->where('ver_title.vernacular', 1);
                })
                ->join('bible_translations as eng_title', function ($join) use ($english_id, $name) {
                    $join->on('eng_title.bible_id', 'bibles.bible_id')->where('eng_title.language_id', $english_id);
                })
                ->when($code, function ($q) use ($code) {
                    $q->where('bible_filesets.id', 'LIKE', '%' . $code)->get();
                })->when($sort, function ($q) use ($sort) {
                    $q->orderBy($sort, 'asc');
                })->select([
                    'eng_title.name as eng_title',
                    'ver_title.name as ver_title',
                    'bible_filesets.id'
                ])->get();
            
            if ($name) {
                $subsetVersions = $versions->where('eng_title', $name)->first();
                if (!$subsetVersions) {
                    $subsetVersions = $versions->where('ver_title', $name)->first();
                }
                $versions = [$subsetVersions];
            }

            return $versions;
        });

        return $this->reply(fractal(
            isset($versions[0]) ? $versions[0] : [],
            new LibraryVolumeTransformer(),
            $this->serializer
        ));
    }

    /**
     * v2_volume_history
     *
     * @link https://api.dbp.test/library/volumehistory?key=1234&v=2
     *
     * @OA\Get(
     *     path="/library/volumehistory",
     *     tags={"Library Catalog"},
     *     summary="Volume History List",
     *     description="This call gets the event history for volume changes to status, expiry, basic info, delivery, and organization association. The event reflects the previous state of the volume. In other words, it reflects the state up to the moment of the time of the event.",
     *     operationId="v2_volume_history",
     *     @OA\Parameter(name="limit",  in="query", description="The Number of records to return", @OA\Schema(type="integer",default=500)),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v2_volume_history")),
     *         @OA\MediaType(mediaType="application/xml",  @OA\Schema(ref="#/components/schemas/v2_volume_history")),
     *         @OA\MediaType(mediaType="text/x-yaml",      @OA\Schema(ref="#/components/schemas/v2_volume_history"))
     *     )
     * )
     *
     * A Route to Review The Last 500 Recent Changes to The Bible Resources
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     */
    public function history()
    {
        $limit  = checkParam('limit') ?? 500;
        $cache_params = [$limit];

        $filesets = cacheRemember('v2_library_history', $cache_params, now()->addDay(), function () use ($limit) {
            $filesets = BibleFileset::with('bible.language')->has('bible.language')->take($limit)->get();
            return $filesets->map(function ($fileset) {
                $v2_id = $fileset->bible->first()->language->iso . substr($fileset->bible->first()->id, 3, 3);
                $fileset->v2_id = strtoupper($v2_id);
                return $fileset;
            });
        });

        return $this->reply(fractal($filesets, new LibraryVolumeTransformer(), $this->serializer));
    }

    /**
     *
     *
     * Display a listing of the bibles.
     *
     * @OA\Get(
     *     path="/library/volume",
     *     tags={"Library Catalog"},
     *     summary="",
     *     description="This method retrieves the available volumes in the system according to the filter specified",
     *     operationId="v2_library_volume",
     *     @OA\Parameter(
     *          name="dam_id",
     *          in="query",
     *          description="The Bible Id",
     *          @OA\Schema(ref="#/components/schemas/Bible/properties/id")
     *     ),
     *     @OA\Parameter(
     *          name="fcbh_id",
     *          in="query",
     *          description="An alternative query name for the bible id",
     *          @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *          name="media",
     *          in="query",
     *          description="If set, will filter results by the type of media for which filesets are available.",
     *         @OA\Schema(
     *          type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *          name="language",
     *          in="query",
     *          description="The language to filter results by. For a complete list see the `name` field in the `/languages` route",
     *          @OA\Schema(ref="#/components/schemas/Language/properties/name")
     *     ),
     *     @OA\Parameter(
     *          name="language_code",
     *          in="query",
     *          description="The iso code to filter results by. This will return results only in the language specified. For a complete list see the `iso` field in the `/languages` route",
     *          @OA\Schema(ref="#/components/schemas/Language/properties/iso")),
     *     @OA\Parameter(
     *          name="language_family_code",
     *          in="query",
     *          description="The iso code of the trade language to filter results by. This will also return all dialects of a language. For a complete list see the `iso` field in the `/languages` route",
     *          @OA\Schema(type="string")),
     *     @OA\Parameter(
     *          name="updated",
     *          in="query",
     *          description="The last time updated",
     *          @OA\Schema(type="string")),
     *     @OA\Parameter(
     *          name="organization_id",
     *          in="query",
     *          description="The owning organization to filter results by. For a complete list see the `/organizations` route",
     *          @OA\Schema(type="string")),
     *     @OA\Parameter(
     *          name="version_code",
     *          in="query",
     *          description="The abbreviated `BibleFileset` id to filter results by.",
     *          @OA\Schema(type="string")),
     *     @OA\Parameter(
     *          name="sort_by",
     *          in="query",
     *          description="The any field to within the bible model may be selected as the value for this `sort_by` param.",
     *          @OA\Schema(type="string")),
     *     @OA\Parameter(
     *          name="sort_dir",
     *          in="query",
     *          description="The direction to sort by the field specified in `sort_by`. Either `asc` or `desc`",
     *          @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v2_library_volume")),
     *         @OA\MediaType(mediaType="application/xml",  @OA\Schema(ref="#/components/schemas/v2_library_volume")),
     *         @OA\MediaType(mediaType="text/csv",         @OA\Schema(ref="#/components/schemas/v2_library_volume")),
     *         @OA\MediaType(mediaType="text/x-yaml",      @OA\Schema(ref="#/components/schemas/v2_library_volume"))
     *     )
     * )
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     */
    public function volume()
    {
        $dam_id             = checkParam('dam_id|fcbh_id');
        $media              = checkParam('media');
        $language_name      = checkParam('language');
        $iso                = checkParam('language_code|language_family_code');
        $updated            = checkParam('updated');
        $organization       = checkParam('organization_id');
        $version_code       = checkParam('version_code');

        $arclight = new ArclightController();
        if ($version_code === 'JFV') {
            return $arclight->volumes();
        }

        $cache_params = [$dam_id, $media, $language_name, $iso, $updated, $organization, $version_code];
        $filesets = cacheRemember('v2_library_volume', $cache_params, now()->addDay(), function () use ($dam_id, $media, $language_name, $iso, $updated, $organization, $version_code) {
            $language_id = $iso ? optional(Language::where('iso', $iso)->first())->id : null;

            $has_nondrama = BibleFileset::where('set_type_code', 'audio')
                ->select('id')
                ->where('set_type_code', 'audio')
                ->whereHas('permissions', function ($query) {
                    $query->whereHas('access', function ($query) {
                        $query->where('name', '!=', 'RESTRICTED');
                    });
                });

            $filesets = BibleFileset::with('meta')->where('set_type_code', '!=', 'text_format')
                ->where('bible_filesets.id', 'NOT LIKE', '%16')
                ->uniqueFileset($dam_id, $media, true)
                ->withBible($language_name, $language_id, $organization)
                ->when($language_id, function ($query) use ($language_id) {
                    $query->whereHas('bible', function ($subquery) use ($language_id) {
                        $subquery->where('language_id', $language_id);
                    });
                })
                ->leftJoin('language_codes as arclight', function ($query) {
                    $query->on('arclight.language_id', 'languages.id')->where('source', 'arclight');
                })
                ->leftJoin(
                    'bible_files_secondary',
                    'bible_files_secondary.hash_id',
                    'bible_filesets.hash_id'
                )
                ->select(
                    \DB::raw(
                        'english_name.name as english_name,
                        autonym.name as autonym_name,
                        bibles.id as bible_id,
                        bible_filesets.id,
                        bible_filesets.created_at,
                        bible_filesets.updated_at,
                        bible_filesets.set_type_code,
                        bible_filesets.set_size_code,
                        bible_filesets.asset_id,
                        alphabets.direction,
                        languages.iso,
                        languages.iso2B,
                        languages.iso2T,
                        languages.iso1,
                        arclight.code as arclight_code,
                        languages.name as language_name,
                        language_translations.name as autonym,
                        MIN(bible_files_secondary.file_name) as secondary_file_name,
                        bible_files_secondary.file_type as secondary_file_type,
                        bible_filesets_has_dram.id as bible_filesets_has_dram'
                    )
                )->groupBy(['bible_filesets.hash_id', 'bible_files_secondary.file_type'])
                ->leftJoinSub($has_nondrama, 'bible_filesets_has_dram', function ($join) {
                    $join
                        ->on('bible_filesets_has_dram.id', '=', 'bible_filesets.id');
                })
                ->when($updated, function ($query) use ($updated) {
                    $query->where('bible_filesets.updated_at', '>', $updated);
                })
                ->when($version_code, function ($query) use ($version_code) {
                    $query->whereRaw('SUBSTRING(bibles.id,4) = ?', [$version_code]);
                })
                ->when($organization, function ($query) use ($organization) {
                    $query->where('bible_organizations.organization_id', $organization);
                })
                ->isContentAvailable($this->key)
                ->get()
                ->filter(function ($item) {
                    return $item->english_name;
                });

            $asset_ids_array = [];
            foreach ($filesets as $fileset) {
                $asset_ids_array[$fileset->asset_id] = true;
            }

            $assets = Asset::whereIn('id', array_keys($asset_ids_array))->get();
            $assets_by_id = [];

            foreach ($assets as $asset) {
                $assets_by_id[$asset->id] = $asset;
            }

            foreach ($filesets as $key => $fileset) {
                if ($fileset && $fileset->secondary_file_name) {
                    $filesets[$key]->secondary_file_path = $this->signedUrl(
                        storagePath(
                            $fileset->bible_id,
                            $fileset,
                            null,
                            $fileset->secondary_file_name
                        ),
                        $fileset->asset_id,
                        random_int(0, 10000000),
                        isset($assets_by_id[$fileset->asset_id]) ? $assets_by_id[$fileset->asset_id] : null
                    );
                }
            }

            return $this->generateV2StyleId($filesets);
        });

        $this->getBiblesByFilesetId($filesets);

        $filesets = fractal($filesets, new LibraryVolumeTransformer(), $this->serializer)->toArray();
        if (!empty($filesets) &&
            !isset($version_code) &&
            (empty($media) || $media === 'video' || $media === 'video_stream') &&
            !empty($iso)
        ) {
            $filesets = array_merge($filesets, $arclight->volumes($iso));
        }

        return $this->reply($filesets);
    }

    private function getBiblesByFilesetId($filesets)
    {
        $filesets_ids = [];
        foreach ($filesets as $fileset) {
            $filesets_ids[] = $fileset->id;
        }

        $bible_filesets_with_bible_id = BibleFileset::whereIn('id', $filesets_ids)
            ->whereIn('set_type_code', ['audio_stream', 'audio_drama_stream', 'audio', 'audio_drama'])
            ->select(
                \DB::raw(
                    'bible_filesets.id,
                    (SELECT bibles.id
                    FROM bibles
                    INNER JOIN bible_fileset_connections ON bible_fileset_connections.bible_id = bibles.id
                    WHERE bible_fileset_connections.hash_id = bible_filesets.hash_id LIMIT 1) as bible_id'
                )
            )->get()
            ->pluck('id', 'bible_id');

        foreach ($filesets as &$fileset) {
            if (isset($bible_filesets_with_bible_id[$fileset->id])) {
                $fileset->bible_id = $bible_filesets_with_bible_id[$fileset->id];
            }
        }
    }

    private function filterById($filesets, $dam_id)
    {
        return array_filter($filesets, function ($fileset) use ($dam_id) {
            return $fileset->generated_id == $dam_id;
        });
    }

    private function generateV2StyleId($filesets)
    {
        $output = [];
        $output = array_merge($output, $this->getV2Output($filesets));
        return $output;
    }

    private function getV2Output($filesets)
    {
        $output = [];
        foreach ($filesets as $fileset) {
            $type_codes = $this->getV2TypeCode($fileset, !empty($fileset->bible_filesets_has_dram));

            foreach ($type_codes as $type_code) {
                $ot_fileset_id = substr($fileset->id, 0, 6) . 'O' . $type_code;
                $nt_fileset_id = substr($fileset->id, 0, 6) . 'N' . $type_code;
                $pt_fileset_id = substr($fileset->id, 0, 6) . 'P' . $type_code;
                switch ($fileset->set_size_code) {
                    case 'C':
                        $output[$ot_fileset_id] = clone $fileset;
                        $output[$ot_fileset_id]->generated_id = $ot_fileset_id;

                        $output[$nt_fileset_id] = clone $fileset;
                        $output[$nt_fileset_id]->generated_id = $nt_fileset_id;
                        break;

                    case 'NT':
                        $output[$nt_fileset_id] = clone $fileset;
                        $output[$nt_fileset_id]->generated_id = $nt_fileset_id;
                        break;

                    case 'OT':
                        $output[$ot_fileset_id] = clone $fileset;
                        $output[$ot_fileset_id]->generated_id = $ot_fileset_id;
                        break;

                    case 'NTOTP':
                    case 'OTNTP':
                    case 'NTPOTP':
                    case 'NTP':
                    case 'OTP':
                        $output[$ot_fileset_id] = clone $fileset;
                        $output[$ot_fileset_id]->generated_id = $pt_fileset_id;
                        break;
                    default:
                        break;
                }
            }
        }

        return $output;
    }

    /**
     * @param $fileset
     *
     * @return string
     */
    private function getV2TypeCode($fileset, $non_drama_exists)
    {
        switch ($fileset->set_type_code) {
            case 'audio_drama':
                return ['2DA'];
                break;
            case 'audio':
                return ['1DA'];
                break;
            case 'text_plain':
                if ($non_drama_exists) {
                    return ['2ET', '1ET'];
                }
                return ['2ET'];
            default:
                return [];
        }
    }
}
