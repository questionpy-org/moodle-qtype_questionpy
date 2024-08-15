<?php
// This file is part of the QuestionPy Moodle plugin - https://questionpy.org
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace qtype_questionpy\package;

defined('MOODLE_INTERNAL') || die;

use moodle_exception;
use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;

/**
 * Represents an available QuestionPy package on the application server.
 *
 * @package    qtype_questionpy
 * @copyright  2024 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_info extends package_base {
    /**
     * Gets the row id if this package is already stored in the DB, or false otherwise.
     *
     * @return false|int either false or the DB id
     * @throws moodle_exception
     */
    public function get_id(): false|int {
        global $DB;
        return $DB->get_field('qtype_questionpy_package', 'id', [
            'shortname' => $this->shortname,
            'namespace' => $this->namespace,
        ]);
    }

    /**
     * Persists a package tag in the database.
     *
     * @param string $tag
     * @return int
     * @throws moodle_exception
     */
    private function store_tag(string $tag): int {
        global $DB;

        $record = ['tag' => strtolower($tag)];

        $id = $DB->get_field('qtype_questionpy_tag', 'id', $record);
        if ($id === false) {
            return $DB->insert_record('qtype_questionpy_tag', $record);
        }
        return $id;
    }

    /**
     * Inserts new package data.
     *
     * @param int $timestamp
     * @return int
     * @throws moodle_exception
     */
    public function insert(int $timestamp): int {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $id = $DB->insert_record('qtype_questionpy_package', [
            'shortname' => $this->shortname,
            'namespace' => $this->namespace,
            'type' => $this->type,
            'author' => $this->author,
            'url' => $this->url,
            'icon' => $this->icon,
            'license' => $this->license,
            'timemodified' => $timestamp,
            'timecreated' => $timestamp,
        ]);

        if ($this->languages) {
            // For each language store the localized package data as a separate record.
            $languagedata = [];
            foreach ($this->languages as $language) {
                $languagedata[] = [
                    'packageid' => $id,
                    'language' => $language,
                    'name' => $this->get_localized_name([$language]),
                    'description' => $this->get_localized_description([$language]),
                ];
            }
            $DB->insert_records('qtype_questionpy_language', $languagedata);
        }

        if ($this->tags) {
            // Store each tag with the package id in the tag table.
            $tagsdata = [];
            foreach ($this->tags as $tag) {
                $tagsdata[] = [
                    'packageid' => $id,
                    'tagid' => $this->store_tag($tag),
                ];
            }
            $DB->insert_records('qtype_questionpy_pkgtag', $tagsdata);
        }

        $transaction->allow_commit();
        return $id;
    }

    /**
     * Updates existing package info.
     *
     * @param int $id
     * @param int $timestamp
     * @throws moodle_exception
     */
    public function update(int $id, int $timestamp): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->update_record('qtype_questionpy_package', [
            'id' => $id,
            'type' => $this->type,
            'author' => $this->author,
            'url' => $this->url,
            'icon' => $this->icon,
            'license' => $this->license,
            'timemodified' => $timestamp,
        ]);

        // We remove every language and tag entry and insert the new ones for simplicity.
        $DB->delete_records('qtype_questionpy_language', ['packageid' => $id]);
        foreach ($this->languages as $language) {
            $DB->insert_record('qtype_questionpy_language', [
                'packageid' => $id,
                'language' => $language,
                'name' => $this->get_localized_name([$language]),
                'description' => $this->get_localized_description([$language]),
            ]);
        }

        $DB->delete_records('qtype_questionpy_pkgtag', ['packageid' => $id]);
        // Store each tag with the package id in the tag table.
        $tagsdata = [];
        foreach ($this->tags as $tag) {
            $tagsdata[] = [
                'packageid' => $id,
                'tagid' => $this->store_tag($tag),
            ];
        }
        $DB->insert_records('qtype_questionpy_pkgtag', $tagsdata);
        $DB->execute("
            DELETE
            FROM {qtype_questionpy_tag}
            WHERE id NOT IN (
                SELECT tagid
                FROM {qtype_questionpy_pkgtag}
            )
        ");

        $transaction->allow_commit();
    }
}

array_converter::configure(package_info::class, function (converter_config $config) {
    $config
        ->rename("hash", "package_hash")
        ->rename("shortname", "short_name")
        // The DB rows are also read using array_converter, but their columns are named differently to the json fields.
        ->alias("hash", "hash")
        ->alias("shortname", "shortname");
});
