<?php

namespace App\Http\Controllers\Wiki;

use App\Http\Controllers\APIController;

use App\Models\Country\JoshuaProject;
use App\Models\Country\Country;
use App\Transformers\CountryTransformer;

class CountriesController extends APIController
{

    /**
     * Returns Countries
     *
     * @version 4
     * @category v4_countries.all
     *
     * @return mixed $countries string - A JSON string that contains the status code and error messages if applicable.
     *
     * @OA\Get(
     *     path="/countries",
     *     tags={"Countries"},
     *     summary="Returns Countries",
     *     description="Returns the List of Countries",
     *     operationId="v4_countries.all",
     *     @OA\Parameter(
     *          name="l10n",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/Language/properties/iso"),
     *          description="When set to a valid three letter language iso, the returning results will be localized in the language matching that iso. (If an applicable translation exists). For a complete list see the `iso` field in the `/languages` route"
     *     ),
     *     @OA\Parameter(
     *          name="has_filesets",
     *          in="query",
     *          @OA\Schema(type="boolean"),
     *          description="Filter the returned countries to those containing languages that have filesets",
     *     ),
     *     @OA\Parameter(
     *          name="include_languages",
     *          in="query",
     *          @OA\Schema(type="string"),
     *          description="When set to true, the return will include the major languages used in each country. You may optionally also include the language names by setting it to `with_names`",
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(
     *            mediaType="application/json",
     *            @OA\Schema(ref="#/components/schemas/v4_countries.all")
     *         )
     *     )
     * )
     *
     *
     */
    public function index()
    {
        $filesets = checkParam('has_filesets') ?? true;
        $languages = checkParam('include_languages');

        $cache_params = [$GLOBALS['i18n_iso'], $filesets, $languages];

        $countries = cacheRemember('countries', $cache_params, now()->addDay(), function () use ($filesets, $languages) {
            $countries = Country::with('currentTranslation')->when($filesets, function ($query) {
                $query->whereHas('languages.bibles.filesets');
            })->get();
            if ($languages !== null) {
                $countries->load([
                    'languagesFiltered' => function ($query) use ($languages) {
                        $query->orderBy('country_language.population', 'desc');
                        if ($languages === 'with_names') {
                            $query->with(['translation' => function ($query) {
                                $query->where('language_translation_id', $GLOBALS['i18n_id']);
                            }]);
                        }
                    },
                ]);
            }
            return fractal()->collection($countries)->transformWith(new CountryTransformer());
        });
        return $this->reply($countries);
    }

    /**
     * Returns Joshua Project Country Information
     *
     * @version 4
     * @category v4_countries.jsp
     *
     * @return mixed $countries string - A JSON string that contains the status code and error messages if applicable.
     *
     */
    public function joshuaProjectIndex()
    {
        $joshua_project_countries = cacheRemember('countries_jp', [$GLOBALS['i18n_iso']], now()->addDay(), function () {
            $countries = JoshuaProject::with([
                'country',
                'translations' => function ($query) {
                    $query->where('language_id', $GLOBALS['i18n_id']);
                },
            ])->get();

            return fractal($countries, CountryTransformer::class);
        });
        return $this->reply($joshua_project_countries);
    }

    /**
     * Returns the Specified Country
     *
     * @version 4
     * @category v4_countries.one
     *
     * @OA\Get(
     *     path="/countries/{id}",
     *     tags={"Countries"},
     *     summary="Returns a single Country",
     *     description="Returns a single Country",
     *     operationId="v4_countries.one",
     *     @OA\ExternalDocumentation(
     *         description="For more information on Country Codes,  please refer to the ISO Registration Authority",
     *         url="https://www.iso.org/iso-3166-country-codes.html"
     *     ),
     *     @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(ref="#/components/schemas/Country/properties/id")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(
     *            mediaType="application/json",
     *            @OA\Schema(ref="#/components/schemas/v4_countries.one")
     *         )
     *     )
     * )
     *
     * @param  string $id
     *
     * @return mixed $countries string - A JSON string that contains the status code and error messages if applicable.
     *
     */
    public function show($id)
    {
        $cache_params = [$id, $GLOBALS['i18n_iso']];
        $country = cacheRemember('countries', $cache_params, now()->addDay(), function () use ($id) {
            $country = Country::with('languagesFiltered.bibles.translations')->find($id);
            if (!$country) {
                return $this->setStatusCode(404)->replyWithError(trans('api.countries_errors_404', ['id' => $id]));
            }
            return $country;
        });

        if (!is_a($country, Country::class)) {
            return $country;
        }

        $includes = $this->loadWorldFacts($country);
        return $this->reply(fractal($country, new CountryTransformer(), $this->serializer)->parseIncludes($includes));
    }

    private function loadWorldFacts($country)
    {
        $loadedProfiles = [];

        // World Factbook
        $profiles['communications'] = checkParam('communications') ?? 0;
        $profiles['economy']        = checkParam('economy') ?? 0;
        $profiles['energy']         = checkParam('energy') ?? 0;
        $profiles['geography']      = checkParam('geography') ?? 0;
        $profiles['government']     = checkParam('government') ?? 0;
        $profiles['issues']         = checkParam('issues') ?? 0;
        $profiles['people']         = checkParam('people') ?? 0;
        $profiles['ethnicities']    = checkParam('ethnicity') ?? 0;
        $profiles['regions']        = checkParam('regions') ?? 0;
        $profiles['religions']      = checkParam('religions') ?? 0;
        $profiles['transportation'] = checkParam('transportation') ?? 0;
        $profiles['joshuaProject']  = checkParam('joshuaProject') ?? 0;
        foreach ($profiles as $key => $profile) {
            if ($profile !== 0) {
                $country->load($key);
                if ($country->{$key} !== null) {
                    $loadedProfiles[] = $key;
                }
            }
        }
        return $loadedProfiles;
    }
}
