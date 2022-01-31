<?php

namespace App\Services\Plans;

use App\Models\Plan\Plan;
use App\Models\Plan\PlanDay;
use App\Models\Plan\UserPlan;
use App\Models\Playlist\Playlist;
use App\Models\Playlist\PlaylistItems;
use App\Models\Bible\Bible;
use App\Services\Plans\PlaylistService;

class PlanService
{
    private $playlist_service;
    public function __construct()
    {
        $this->playlist_service = new PlaylistService();
    }

    /**
     * Create and get a new plan from a given bible
     *
     * @param int $plan_id
     * @param Bible $bible
     * @param int $user_id
     * @param int $draft
     */
    public function translate(
        int $plan_id,
        Bible $bible,
        int $user_id = 0,
        bool $draft = true
    ) : Plan {
        $plan = $this->getPlanWithDaysByIdAndUser($plan_id, $user_id);

        $plan_data = [
            'user_id'               => $user_id,
            'name'                  => $plan->name . ': ' . $bible->language->name . ' ' . substr($bible->id, -3),
            'featured'              => false,
            'draft'                 => $draft,
            'suggested_start_date'  => $plan->suggested_start_date,
            'thumbnail'             => $plan->thumbnail,
            'language_id'           => $bible->language_id,
        ];

        $new_plan = Plan::create($plan_data);
        $translation_data = [];
        $translated_percentage = 0;
        $play_day_data = [];
        $audio_fileset_types = collect(['audio_stream', 'audio_drama_stream', 'audio', 'audio_drama']);
        $bible_audio_filesets = $bible->filesets->whereIn('set_type_code', $audio_fileset_types);
        $count_plan_days = 0;
        $playlist_ids = [];

        foreach ($plan->days as $day) {
            $playlist_ids[] = $day->playlist_id;
        }

        $playlists = Playlist::findByUserAndIds($user_id, $playlist_ids);

        $playlists_to_create = [];
        $translated_items = [];
        foreach ($plan->days as $day) {
            if (isset($playlists[$day->playlist_id])) {
                $playlist_by_day = $playlists[$day->playlist_id];
                $playlist_translated =$this->translatePlaylist(
                    $playlist_by_day,
                    $user_id,
                    $new_plan->id,
                    $bible,
                    $audio_fileset_types,
                    $bible_audio_filesets
                );
                $playlists_to_create[] = $playlist_translated['playlist_data'];
                $translation_data[$day->playlist_id] = $playlist_translated["translation_data"];
                $translated_percentage += $playlist_translated["translated_percentage"];
                $translated_items[$day->playlist_id] = $playlist_translated["translated_items"];

                if ($day->hasContentAvailable($playlist_by_day)) {
                    $count_plan_days += 1;
                }
            }
        }

        Playlist::insert($playlists_to_create);

        $new_playlists = Playlist::findByUserAndPlan($user_id, $new_plan->id);

        $new_day_playlist_ids = [];
        $order = 1;

        foreach ($new_playlists as $new_palyslist_index => $new_playlist) {
            $play_day_data[] = [
                'plan_id'               => $new_plan->id,
                'playlist_id'           => $new_playlist->id,
                'order_column'          => $order,
            ];
            $order += 1;
            $new_day_playlist_ids[$playlist_ids[$new_palyslist_index]] = $new_playlist->id;
        }

        $playlist_items_to_create = [];
        $playlist_items_to_create_indexed = [];

        foreach ($translated_items as $playlist_id_key => $playlist_translated_item) {
            $order = 0;
            foreach ($playlist_translated_item as $translated_item) {
                $new_playlist_id_key = $new_day_playlist_ids[$playlist_id_key];
                $playlist_item_data = [
                    'playlist_id'   => $new_playlist_id_key,
                    'fileset_id'    => $translated_item['fileset_id'],
                    'book_id'       => $translated_item['book_id'],
                    'chapter_start' => $translated_item['chapter_start'],
                    'chapter_end'   => $translated_item['chapter_end'],
                    'verse_start'   => $translated_item['verse_start'] ?? null,
                    'verse_end'     => $translated_item['verse_end'] ?? null,
                    'verses'        => $translated_item['verses'] ?? 0,
                    'order_column'  => $translated_item['order_column'] ?? $order
                ];
                $key_translated = implode('-', $playlist_item_data);
                $playlist_items_to_create_indexed[$new_playlist_id_key][$key_translated] = [
                    "translated_id" => $translated_item['translated_id'],
                    "playlist_id_key" => $playlist_id_key
                ];
                $playlist_items_to_create[] = $playlist_item_data;
                $order += 1;
            }
        }

        PlaylistItems::insert($playlist_items_to_create);

        $new_items = PlaylistItems::findByIdsWithFilesetRelation($new_day_playlist_ids);

        foreach ($new_items as $new_playlist_item) {
            $key_translated = $new_playlist_item->generateUniqueKey();

            if (isset($playlist_items_to_create_indexed[$new_playlist_item->playlist_id][$key_translated])) {
                $translated_item_id = $playlist_items_to_create_indexed
                    [$new_playlist_item->playlist_id][$key_translated]['translated_id'];
                $translated_playlist_id = $playlist_items_to_create_indexed
                    [$new_playlist_item->playlist_id][$key_translated]['playlist_id_key'];

                if (isset($translation_data[$translated_playlist_id][$translated_item_id])) {
                    $translation_data[$translated_playlist_id][$translated_item_id]->translation_item =
                        $new_playlist_item;
                }
            }
        }

        PlanDay::insert($play_day_data);
        $translated_percentage = $count_plan_days > 0
            ? $translated_percentage / $count_plan_days
            : 0;

        UserPlan::create([
            'user_id'               => $user_id,
            'plan_id'               => $new_plan->id
        ]);

        $plan = $this->getPlanWithDaysByIdAndUser($new_plan->id, $user_id);

        $plan->translation_data = $this->transformTranslationData($translation_data);
        $plan->translated_percentage = $translated_percentage*100;

        return $plan;
    }

    /**
     * For each play day that belong to a Plan, it will attached the correct playlist for the given Plan Object
     *
     * @param Plan $plan
     * @param int $user_id
     *
     * @return void
     */
    public function setPlaylistItemsForEachPlaylist(Plan $plan, int $user_id) : void
    {
        $new_day_playlist_ids = [];

        foreach ($plan->days as $day) {
            $new_day_playlist_ids[] = $day->playlist_id;
        }

        $playlists = Playlist::findWithFollowersByUserAndIds($user_id, $new_day_playlist_ids);

        foreach ($plan->days as $day) {
            if (isset($playlists[$day->playlist_id])) {
                $day->playlist = $playlists[$day->playlist_id];
            }
        }
    }

    /**
     * For each play list item that belong to a Plan, it will create the verse_text property for the given Plan Object
     *
     * @param Plan $plan
     *
     * @return void
     */
    public function setVerseTextToEachPlaylistItem(Plan $plan) : void
    {
        foreach ($plan->days as $day) {
            foreach ($day->playlist->items as $item) {
                $item->verse_text = $item->getVerseText();
            }
        }
    }

    /**
     * Transform into an array a plan translation data
     *
     * @param Array $translation_data
     *
     * @return Array
     */
    private function transformTranslationData(?Array $translation_data) : Array
    {
        $new_translation_data = [];

        if ($translation_data) {
            foreach ($translation_data as $translation_data_playlist) {
                $new_translation_data_item = [];
                foreach ($translation_data_playlist as $translation_data_item) {
                    $new_translation_data_item[] = $translation_data_item;
                }
                $new_translation_data[] = $new_translation_data_item;
            }
        }

        return $new_translation_data;
    }

    /**
     * Get all data related with the translated playlist
     *
     * @param Playlist $playlist
     * @param int $user_id
     * @param int $plan_id
     * @param Bible $bible
     * @param Collection $audio_fileset_types
     * @param Collection $bible_audio_filesets
     *
     * @return Plan
     */
    private function translatePlaylist(
        $playlist,
        $user_id,
        $plan_id,
        $bible,
        $audio_fileset_types,
        $bible_audio_filesets
    ) : Array {
        $translated_items = [];
        $metadata_items = [];
        $total_translated_items = 0;
        
        if (isset($playlist->items)) {
            foreach ($playlist->items as $item) {
                if (isset($item->fileset, $item->fileset->set_type_code)) {
                    $item->fileset = formatFilesetMeta($item->fileset);
                    $ordered_types = $audio_fileset_types->filter(function ($type) use ($item) {
                        return $type !== $item->fileset->set_type_code;
                    })->prepend($item->fileset->set_type_code);
                    $preferred_fileset = $ordered_types->map(
                        function ($type) use ($bible_audio_filesets, $item) {
                            return $this->playlist_service->getFileset(
                                $bible_audio_filesets,
                                $type,
                                $item->fileset->set_size_code
                            );
                        }
                    )->firstWhere('id');
                    $has_translation = isset($preferred_fileset);
                    $is_streaming = true;

                    if ($has_translation) {
                        $item->fileset_id = $preferred_fileset->id;
                        $is_streaming = $preferred_fileset->set_type_code === 'audio_stream'
                            || $preferred_fileset->set_type_code === 'audio_drama_stream';
                        $translated_items[$item->id] = [
                            'translated_id' => $item->id,
                            'fileset_id' => $item->fileset_id,
                            'book_id' => $item->book_id,
                            'chapter_start' => $item->chapter_start,
                            'chapter_end' => $item->chapter_end,
                            'verse_start' => $is_streaming ? $item->verse_start : null,
                            'verse_end' => $is_streaming ? $item->verse_end : null,
                            'order_column' => $item->order_column,
                            'duration' => $item->duration,
                            'verses' => $item->verses,
                        ];
                        $total_translated_items += 1;
                    }
                }
                $metadata_items[$item->id] = $item;
            }

            $translated_percentage = sizeof($playlist->items) ? $total_translated_items / sizeof($playlist->items) : 0;
        }

        return [
            "playlist_data" =>
                [
                    'user_id'           => $user_id,
                    'name'              => $playlist->name . ': ' . $bible->language->name . ' ' . substr($bible->id, -3),
                    'external_content'  => $playlist->external_content,
                    'featured'          => false,
                    'draft'             => true,
                    'plan_id'           => $plan_id
                ],
            "translation_data" => $metadata_items,
            "translated_items" => $translated_items,
            "translated_percentage" => $translated_percentage
        ];
    }

    /**
     * Get a plan by ID with the Days and User relations
     *
     * @param int $plan_id
     * @param int $user_id
     *
     * @return Plan
     */
    public function getPlanWithDaysByIdAndUser(int $plan_id, int $user_id = null) : Plan
    {
        return Plan::getWithDaysAndUserById($plan_id, $user_id);
    }

    /**
     * Get a plan by ID
     *
     * @param int $plan_id
     *
     * @return Plan
     */
    public function getPlanById(int $plan_id) : ?Plan
    {
        return Plan::findOne($plan_id);
    }

    /**
     * Calculate the duration and verses values for each playlist item that belong to plan
     *
     * @param int $plan_id
     *
     * @return Plan
     */
    public function calculateDurationAndVersesUpdatePlan(Plan $plan, bool $save = false) : Plan
    {
        $playlist_ids = [];
        foreach ($plan->days as $day) {
            $playlist_ids[] = $day->playlist_id;
        }

        $playlist_items = PlaylistItems::whereIn('playlist_id', $playlist_ids)->get();
        $this->playlist_service->calculateDurationAndUpdateItem($playlist_items, $save);
        $this->playlist_service->calculateVersesAndUpdateItem($playlist_items, $save);

        if ($save === true) {
            foreach ($playlist_items as $playlist_item) {
                $playlist_item->save();
            }
        }

        return $plan;
    }
}
