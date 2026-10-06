<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'mongodb';

    /**
     * Books whose cover_image / cover_image_thumb point to a placeholder
     * service (via.placeholder.com, etc.) were incorrectly marked
     * has_cover=true.  Fix them to has_cover=false.
     */
    public function up(): void
    {
        $books = DB::connection('mongodb')->getCollection('books');

        $placeholderHosts = [
            'via.placeholder.com',
            'placeholder.com',
            'placehold.co',
            'placehold.it',
            'placekitten.com',
            'dummyimage.com',
        ];

        $regex = implode('|', array_map(fn ($h) => preg_quote($h, '/'), $placeholderHosts));

        // Set has_cover=false where BOTH cover fields are either
        // empty/null or match a placeholder host.
        $isPlaceholderOrEmpty = [
            '$or' => [
                ['$eq' => ['$$field', null]],
                ['$eq' => ['$$field', '']],
                ['$regexMatch' => ['input' => '$$field', 'regex' => $regex, 'options' => 'i']],
            ],
        ];

        $books->updateMany(
            [
                '$expr' => [
                    '$and' => [
                        ['$let' => ['vars' => ['field' => '$cover_image'], 'in' => $isPlaceholderOrEmpty]],
                        ['$let' => ['vars' => ['field' => '$cover_image_thumb'], 'in' => $isPlaceholderOrEmpty]],
                    ],
                ],
                'has_cover' => true,
            ],
            ['$set' => ['has_cover' => false]]
        );
    }

    public function down(): void
    {
        // Cannot reliably reverse — the original migration already set
        // has_cover based on non-empty URL presence.
    }
};
