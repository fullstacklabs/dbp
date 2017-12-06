<?php

namespace App\Transformers;

use App\Models\Bible\Book;
use Faker\Provider\Base;

class BooksTransformer extends BaseTransformer
{
    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform($book)
    {
	    switch ($this->version) {
		    case "jQueryDataTable": return $this->transformForDataTables($book);
		    case "2": return $this->transformForV2($book);
		    case "3": return $this->transformForV3($book);
		    case "4":
		    default: return $this->transformForV4($book);
	    }
    }

    public function transformForV2($book) {

		switch($this->route) {
			case "v2_library_bookOrder": {
				return [
					"book_order"  => (string) $book->book_order,
					"book_id"     => $book->id,
					"book_name"   => $book->name,
					"dam_id_root" => $book->bible_id
				];
			}

			case "v2_library_book": {
				return [
					"dam_id"             => $book->bible_id.substr($book->book_testament,0,1),
					"book_id"            => $book->id_osis,
					"book_name"          => $book->name,
					"book_order"         => (string) $book->book_order,
					"number_of_chapters" => (string) count($book->sophia_chapters),
					"chapters"           => implode(",",$book->sophia_chapters)
				];
			}

			case "v2_library_chapter": {
				return [
					"dam_id"           => $book->bible_id,
                    "book_id"          => $book->book->id_osis,
                    "chapter_id"       => (string) $book->chapter,
                    "chapter_name"     => "Chapter " . $book->chapter,
                    "default"          => ""
				];
			}

		}
    }

    public function transformForV3(Book $book) {
	    switch ( $this->route ) {
		    case "v3_search": {
		    	$manufactured_id = strval(random_int(0,20000));
			    return [
				    "id"           => $manufactured_id,
				    "name"         => $book->name,
				    "book_code"    => $book->id,
				    "created_at"   => $book->created_at->toDateTimeString(),
				    "updated_at"   => $book->updated_at->toDateTimeString(),
				    "sort_order"   => strval($book->book_order),
				    "volume_id"    => "3070",
				    "enabled"      => "1",
				    "dam_id"       => $book->bible_id,
				    "chapter_list" => implode( ",", $book->sophia_chapters ),
				    "_links"       => [
					    "self" => [ "href" => "http://v3.dbt.io/search/$manufactured_id" ]
				    ]
			    ];
		    }

		    case "v3_books": {
			    $manufactured_id = strval(random_int(0,20000));
		    	return [
				    "id"           => $manufactured_id,
                    "name"         => $book->name,
                    "dam_id"       => $book->bible_id,
                    "book_code"    => $book->id,
                    "order"        => strval($book->book_order),
                    "enabled"      => true,
				    "chapters"     => $book->chapters,
				    "chapter_list" => $book->chapters->pluck('number')->implode(','),
				    "_links"       => [
					    "self" => [ "href" => "http://v3.dbt.io/search/$manufactured_id" ]
				    ]
			    ];
		    }

	    }
    }

	public function transformForV4(Book $book) {
		return [
			$book
		];
	}

}
