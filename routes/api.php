<?php

	// VERSION 2
	Route::name('v2_library_asset')->get('library/asset',                                 'HomeController@libraryAsset');
	Route::name('v2_library_version')->get('library/version',                             'BiblesController@libraryVersion');
	Route::name('v2_library_book')->get('library/book',                                   'BooksController@show');
	Route::name('v2_library_bookName')->get('library/bookname',                           'BooksController@bookNames');
	Route::name('v2_library_bookOrder')->get('library/bookorder',                         'BooksController@show');
	Route::name('v2_library_chapter')->get('library/chapter',                             'BooksController@chapters');
	Route::name('v2_library_language')->get('library/language',                           'LanguagesController@index');
	Route::name('v2_library_verseInfo')->get('library/verseinfo',                         'VerseController@info');
	Route::name('v2_library_numbers')->get('library/numbers',                             'NumbersController@customRange');
	Route::name('v2_library_metadata')->get('library/metadata',                           'BiblesController@libraryMetadata');
	Route::name('v2_library_volume')->get('library/volume',                               'BiblesController@index');
	Route::name('v2_library_volumeLanguage')->get('library/volumelanguage',               'LanguagesController@volumeLanguage');
	Route::name('v2_library_volumeLanguageFamily')->get('library/volumelanguagefamily',   'LanguagesController@volumeLanguageFamily');
	Route::name('v2_volume_organization_list')->get('library/volumeorganization',         'OrganizationsController@index');
	Route::name('v2_volume_history')->get('library/volumehistory',                        'BiblesController@history');
	Route::name('v2_library_organization')->get('library/organization',                   'OrganizationsController@index');
	Route::name('v2_audio_location')->get('audio/location',                               'AudioController@location');
	Route::name('v2_audio_path')->get('audio/path',                                       'AudioController@index');
	Route::name('v2_audio_timestamps')->get('audio/versestart',                           'AudioController@timestampsByReference');
	Route::name('v2_text_font')->get('text/font',                                         'TextController@fonts');
	Route::name('v2_text_verse')->get('text/verse',                                       'TextController@index');
	Route::name('v2_text_search')->get('text/search',                                     'TextController@search');
	Route::name('v2_text_search_group')->get('text/searchgroup',                          'TextController@searchGroup');
	Route::name('v2_video_location')->get('video/location',                               'FilmsController@location');
	Route::name('v2_video_video_path')->get('video/path',                                 'FilmsController@videoPath');
	Route::name('v2_country_lang')->get('country/countrylang',                            'LanguagesController@CountryLang');
	Route::name('v2_api_versionLatest')->get('api/apiversion',                            'HomeController@versionLatest');
	Route::name('v2_api_apiReply')->get('api/reply',                                      'HomeController@versionReplyTypes');

	// VERSION 3
	// What can man do against such reckless hate
	Route::prefix('v3')->group(function () {
		Route::name('v3_query')->get('search',                                           'V3Controller@search');
		Route::name('v3_books')->get('books',                                            'V3Controller@books');
	});


	// VERSION 4
	Route::name('v4_bible.all')->get('bibles',                                            'BiblesController@index');
	Route::name('v4_bible.one')->get('bibles/{id}',                                       'BiblesController@show');
	Route::name('v4_bible.equivalents')->get('bible/{id}/equivalents',                    'BiblesController@equivalents');
	Route::name('v4_bible.books_all')->get('bible/{id}/book/{book}',                      'BiblesController@books');
	Route::name('v4_bible.books_one')->get('bible/{id}/book/{book}',                      'BiblesController@book');
	Route::name('v4_bible.read')->get('bible/{id}/{book}/{chapter}',                      'TextController@index');
	Route::name('v4_bible_books.all')->get('bibles/books/',                               'BooksController@index');
	Route::name('v4_bible_books.one')->get('bibles/books/{id}',                           'BooksController@show');
	Route::name('v4_bible_filesets.all')->get('bibles/{id}/filesets',                     'BibleFileSetsController@index');
	Route::name('v4_bible_filesets.one')->get('bibles/{id}/filesets/{fileset_id}',        'BibleFileSetsController@show');
	Route::name('v4_bible_filesets.permissions')->get('bibles/filesets/{id}/permissions', 'BibleFileSetPermissionsController@index');
	Route::name('v4_bibleFiles.one')->get('bibles/files/{ id }',                          'BibleFilesController@show');
	Route::name('v4_timestamps')->get('timestamps',                                       'AudioController@availableTimestamps');
	Route::name('v4_timestamps.tag')->get('timestamps/{id}',                              'AudioController@timestampsByTag');
	Route::name('v4_timestamps.verse')->get('timestamps/{id}/{book}/{chapter}',           'AudioController@timestampsByReference');
	Route::name('v4_countries.all')->get('countries',                                     'CountriesController@index');
	Route::name('v4_countries.one')->get('countries/{id}',                                'CountriesController@show');
	Route::name('v4_languages.all')->get('languages',                                     'LanguagesController@index');
	Route::name('v4_languages.one')->get('languages/{id}',                                'LanguagesController@show');
	Route::name('v4_alphabets.all')->get('alphabets',                                     'AlphabetsController@index');
	Route::name('v4_alphabets.one')->get('alphabets/{id}',                                'AlphabetsController@show');
	Route::name('v4_numbers.range')->get('numbers/range',                                 'NumbersController@customRange');
	Route::name('v4_numbers.all')->get('numbers/',                                        'NumbersController@index');
	Route::name('v4_numbers.one')->get('numbers/{id}',                                    'NumbersController@show');
	Route::name('v4_organizations.all')->get('organizations/',                            'OrganizationsController@index');
	Route::name('v4_organizations.one')->get('organizations/{id}',                        'OrganizationsController@show');
	Route::name('v4_users.all')->get('organizations/',                                    'UsersController@index');
	Route::name('v4_users.one')->get('organizations/{id}',                                'UsersController@show');
	Route::name('v4_api.versions')->get('/api/versions',                                  'HomeController@versions');
	Route::name('v4_api.versionLatest')->get('/api/versions/latest',                      'HomeController@versionLatest');
	Route::name('v4_api.replyTypes')->get('/api/versions/replyTypes',                     'HomeController@versionReplyTypes');
	Route::name('v4_api.sign')->get('sign',                                               'HomeController@signedUrls');