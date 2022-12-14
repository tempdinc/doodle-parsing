<?php

namespace App\Classes;

use PDO;
use PDOException;

/**
 * Class for MySQL
 */
class MySQL
{
    // A variable for PDO class instance
    public $pdo;
    protected $base;
    protected $host;

    /**
     * A builder with the connection to MYSQL
     */
    public function __construct(string $base, string $host)
    {
        if ($base == 'wp') {
            $dbname = env('MYSQL_WPDB', 'wp_tempd');
        } else {
            $dbname = env('MYSQL_DBNAME', 'parsing');
        }
        if ($host == 'remote') {
            $hostname = env('MYSQL_REMOTEHOST', '127.0.0.1');
        } else {
            $hostname = env('MYSQL_HOST', '127.0.0.1');
        }
        try {
            $this->pdo = new PDO(
                "mysql:host=" . $hostname . ";dbname=" . $dbname . ";charset=utf8",
                env('MYSQL_USER', 'root'),
                env('MYSQL_PASSWORD', '')
            );
            $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Breaking connection to the db 
     */
    public function __destruct()
    {
        $this->pdo = null;
    }

    /**
     * Inserting the records
     *
     * @param  string $table
     * @param  $param
     * @return array
     */
    public function insert($table, $param)
    {
        $param['date_added'] = date('Y-m-d H:i:s');

        $sql = sprintf(
            'insert into %s (%s) values (%s)',
            $table,
            implode(', ', array_keys($param)),
            ':' . implode(', :', array_keys($param))
        );

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($param);
            return [$this->pdo->lastInsertId(), 'create'];
        } catch (\Exception $exception) {
            die($exception->getMessage());
        }
    }

    /**
     * Count records
     *
     * @param  string $table
     * @param  $param
     * @return int
     */
    public function count($table, $params)
    {
        $set = '';
        $x = 1;

        foreach ($params as $param) {
            $set .= "{$param['field']} " . $param['compare'] . " {$param['value']}";
            if ($x < count($params)) {
                $set .= ' AND ';
            }
            $x++;
        }

        $query = sprintf(
            "SELECT COUNT(*) FROM %s WHERE %s",
            $table,
            $set
        );
        // echo $query;
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute();
            return $statement->fetchColumn();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Updating the records
     *
     * @param  string $table
     * @param  $param
     * @return array
     */
    public function update($table, $param)
    {
        $set = '';
        $x = 1;

        $param['last_update'] = date('Y-m-d H:i:s');

        foreach ($param as $field => $value) {
            $set .= "{$field} = :$field";
            if ($x < count($param)) {
                $set .= ',';
            }
            $x++;
        }

        $query = sprintf(
            "UPDATE %s SET %s WHERE link = '%s'",
            $table,
            $set,
            $param['link']
        );
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute($param);
            return [$this->searchForLink($table, $param['link']), 'update'];
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Creating or updating the db record
     *
     * @param  string $table
     * @param  $param
     * @return int
     */
    public function updateOrCreate($table, $param)
    {
        if ($this->checkLinkRental($param['link']) === 0) {
            return $this->insert($table, $param);
        } else {
            return $this->update($table, $param);
        }
    }

    /**
     * List all rz_amenities
     *
     * @param  int $id
     * @param  string $table
     */
    public function listRzAmenities()
    {
        $query = $this->pdo->prepare(
            "SELECT * FROM `wp_term_taxonomy` wtt LEFT JOIN `wp_terms` wt ON wtt.term_id = wt.term_id WHERE wtt.taxonomy = 'rz_amenities'"
        );
        $query->execute();
        return $query->fetchAll();
    }

    /**
     * Removing Amenities with records update
     *
     * @param  int $id
     * @param  string $table
     */
    public function deleteAmenities($id, $table = 'amenities')
    {
        $ids = $this->searchForId($table, $id);

        foreach ($ids as $id) {
            $query = $this->pdo->prepare("DELETE FROM $table WHERE id=?");
            $query->execute([$id->id]);
        }
    }

    /**
     * Removing availability with records update
     *
     * @param  int $id
     * @param  string $table
     */
    public function deleteAvailability($id, $table = 'availability')
    {
        $ids = $this->searchForId($table, $id);

        foreach ($ids as $id) {
            $query = $this->pdo->prepare("DELETE FROM $table WHERE id=?");
            $query->execute([$id->id]);
        }
    }

    /**
     * Getting all records by the difference in date and by is_deleted = 0
     *
     * @param  string $table
     * @return array
     */
    public function getAllRecordsDate($table)
    {
        try {
            $dateNow = date('Y-m-d H:i:s');
            $query = $this->pdo->prepare(
                "SELECT * FROM $table WHERE (TIMESTAMPDIFF(day, $table.last_update, '$dateNow') >= "
                    . env('DIFF_OF_DAYS', 0) .
                    ") AND is_deleted = 0"
            );
            $query->execute();
            return $query->fetchAll();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Getting count of all records WHERE is_deleted = 0 AND post_id IS NOT NULL
     *
     * @return int
     */
    public function countRecordsWithPosts()
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT count(*) FROM `properties` WHERE (is_deleted = 0 AND post_id IS NOT NULL) OR (is_deleted IS NULL AND post_id IS NOT NULL) LIMIT 1"
            );
            $query->execute([]);
            return $query->fetchColumn();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Getting all records by is_deleted = 0 AND post_id IS NOT NULL
     *
     * @param  int $limit_start
     * @param  int $limit
     * @return array
     */
    public function getRecordsWithPosts($limit_start, $limit)
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT * FROM `properties` WHERE (is_deleted = 0 AND post_id IS NOT NULL) OR (is_deleted IS NULL AND post_id IS NOT NULL) LIMIT ?,?"
            );
            $query->execute([$limit_start, $limit]);
            return $query->fetchAll();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Getting count of all records WHERE is_deleted = 0 AND post_id IS NULL
     *
     * @return int
     */
    public function countAllNewRecords()
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT count(*) FROM `properties` WHERE (is_deleted = 0 AND post_id IS NULL) OR (is_deleted IS NULL AND post_id IS NULL) LIMIT 1"
            );
            $query->execute([]);
            return $query->fetchColumn();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Getting all records by is_deleted = 0 AND post_id IS NULL
     *
     * @param  int $limit_start
     * @param  int $limit
     * @return array
     */
    public function getAllNewRecords($limit_start, $limit)
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT * FROM `properties` WHERE (is_deleted = 0 AND post_id IS NULL) OR (is_deleted IS NULL AND post_id IS NULL) LIMIT ?,?"
            );
            $query->execute([$limit_start, $limit]);
            return $query->fetchAll();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Getting unique property_id from availability
     *
     * @return array
     */
    public function getAllUniquePropertyID()
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT DISTINCT (property_id) FROM `availability` WHERE post_id IS NOT NULL"
            );
            $query->execute();
            return $query->fetchAll();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Getting all records from availability with post_id IS NOT NULL by property_id
     *
     * @param  string $property_id
     * @return array
     */
    public function getAllAvailabilityWithPostByProperty($property_id)
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT * FROM `availability` WHERE post_id IS NOT NULL AND property_id = ? ORDER BY id DESC"
            );
            $query->execute([$property_id]);
            return $query->fetchAll();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Getting all records from availability by property_id
     *
     * @param  string $property_id
     * @return array
     */
    public function getAvailabilityNowByProperty($property_id)
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT * FROM `availability` WHERE (property_id = ? AND status = 'Available Now' AND is_deleted IS NULL AND post_id IS NULL) OR (property_id = ? AND status = 'Move In Ready' AND is_deleted IS NULL AND post_id IS NULL) OR (property_id = ? AND status = 'Move-In Ready' AND is_deleted IS NULL AND post_id IS NULL) OR (property_id = ? AND status = 'Now' AND is_deleted IS NULL AND post_id IS NULL)"
            );
            $query->execute([$property_id, $property_id, $property_id, $property_id]);
            return $query->fetchAll();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Getting record from availability by post_id
     *
     * @param  bigint $post_id
     * @return array
     */
    public function getAvailabilityByPostId($post_id)
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT * FROM `availability` WHERE post_id = ?"
            );
            $query->execute([$post_id]);
            return $query->fetchAll();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Getting all records from availability LEFT JOIN properties with status Move In Ready & is_deleted IS NULL & post_id IS NULL & listing_price IS NOT NULL
     *
     * @return array
     */
    public function getAvailability()
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT a.id AS av_id, a.post_id AS av_post_id, a.is_deleted AS av_is_deleted, a.property_id AS av_property_id, a.bedroom_cnt AS av_bedroom_cnt, a.bathroom_cnt as av_bathroom_cnt, a.listing_price AS av_listing_price, a.home_size_sq_ft AS av_home_size_sq_ft, a.lease_length AS av_lease_length, a.status AS av_status, a.image_urls AS av_image_urls, a.date_added AS a_date_added, p.* FROM `availability` a LEFT JOIN `properties` p ON a.property_id = p.id WHERE (a.status = 'Available Now' AND a.is_deleted IS NULL AND a.post_id IS NULL AND a.listing_price IS NOT NULL) OR (a.status = 'Move In Ready' AND a.is_deleted IS NULL AND a.post_id IS NULL AND a.listing_price IS NOT NULL) OR (a.status = 'Move-In Ready' AND a.is_deleted IS NULL AND a.post_id IS NULL AND a.listing_price IS NOT NULL) OR (a.status = 'Now' AND a.is_deleted IS NULL AND a.post_id IS NULL AND a.listing_price IS NOT NULL)"
            );
            $query->execute();
            return $query->fetchAll();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Getting all records from availability LEFT JOIN properties with status Move In Ready & is_deleted IS NULL & post_id IS NULL & listing_price IS NOT NULL by city LIKE & Limit
     *
     * @param  string $property_id
     * @return array
     */
    public function getAvailabilityByCity($city, $limit)
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT a.id AS av_id, a.post_id AS av_post_id, a.is_deleted AS av_is_deleted, a.property_id AS av_property_id, a.bedroom_cnt AS av_bedroom_cnt, a.bathroom_cnt as av_bathroom_cnt, a.listing_price AS av_listing_price, a.home_size_sq_ft AS av_home_size_sq_ft, a.lease_length AS av_lease_length, a.status AS av_status, a.image_urls AS av_image_urls, a.date_added AS a_date_added, p.* FROM `availability` a LEFT JOIN `properties` p ON a.property_id = p.id WHERE (p.city LIKE ? AND a.status = 'Available Now' AND a.is_deleted IS NULL AND a.post_id IS NULL AND a.listing_price IS NOT NULL AND a.image_urls IS NOT NULL AND a.image_urls != '') OR (p.city LIKE ? AND a.status = 'Move In Ready' AND a.is_deleted IS NULL AND a.post_id IS NULL AND a.listing_price IS NOT NULL AND a.image_urls IS NOT NULL AND a.image_urls != '') OR (p.city LIKE ? AND a.status = 'Move-In Ready' AND a.is_deleted IS NULL AND a.post_id IS NULL AND a.listing_price IS NOT NULL AND a.image_urls IS NOT NULL AND a.image_urls != '') OR (p.city LIKE ? AND a.status = 'Now' AND a.is_deleted IS NULL AND a.post_id IS NULL AND a.listing_price IS NOT NULL AND a.image_urls IS NOT NULL AND a.image_urls != '') ORDER BY a.id DESC LIMIT ?"
            );
            $query->execute([$city, $city, $city, $city, $limit]);
            return $query->fetchAll();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Getting id, post_id, property_id from availability WHERE post_id IS NOT NULL
     * 
     * @return array
     */
    public function getAvailabilityWithPost()
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT id, post_id, property_id FROM `availability` WHERE post_id IS NOT NULL"
            );
            $query->execute();
            return $query->fetchAll();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Getting all records from availability LEFT JOIN properties WHERE post_id IS NOT NULL & source RentProgress.com
     *
     * @return array
     */
    public function getAvailabilityWithPostWithPropertySourceRentprogress()
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT a.id AS av_id, a.post_id AS av_post_id, a.is_deleted AS av_is_deleted, a.property_id AS av_property_id, a.bedroom_cnt AS av_bedroom_cnt, a.bathroom_cnt as av_bathroom_cnt, a.listing_price AS av_listing_price, a.home_size_sq_ft AS av_home_size_sq_ft, a.lease_length AS av_lease_length, a.status AS av_status, a.image_urls AS av_image_urls, a.date_added AS a_date_added, p.* FROM `availability` a LEFT JOIN `properties` p ON a.property_id = p.id WHERE a.post_id IS NOT NULL AND p.source = 'rentprogress.com'"
            );
            $query->execute();
            return $query->fetchAll();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Getting id FROM wp_posts LEFT JOIN meta_value FROM wp_postmeta WHERE wp.post_type = 'rz_listing' AND wppm.meta_key = 'rz_listing_type' BY meta_value
     *
     * @param  string $listing_type
     * @return int
     */
    public function countPostsRZListing($listing_type)
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT count(*) FROM `wp_posts` wp LEFT JOIN `wp_postmeta` wppm ON wp.id = wppm.post_id WHERE wp.post_type = 'rz_listing' AND wppm.meta_key = 'rz_listing_type' AND wppm.meta_value = ? LIMIT 1"
            );
            $query->execute([$listing_type]);
            return $query->fetchColumn();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Getting id FROM wp_posts LEFT JOIN meta_value FROM wp_postmeta WHERE wp.post_type = 'rz_listing' AND wppm.meta_key = 'rz_listing_type' BY meta_value LIMIT ?,?
     *
     * @param  string $listing_type
     * @param  int $limit_start
     * @param  int $limit 
     * @return array
     */
    public function getPostsRZListing($listing_type, $limit_start, $limit)
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT wp.id, wppm.meta_value FROM `wp_posts` wp LEFT JOIN `wp_postmeta` wppm ON wp.id = wppm.post_id WHERE wp.post_type = 'rz_listing' AND wppm.meta_key = 'rz_listing_type' AND wppm.meta_value = ? ORDER BY wp.id ASC LIMIT ?,?"
            );
            $query->execute([$listing_type, $limit_start, $limit]);
            return $query->fetchAll();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Getting id FROM wp_posts WHERE wp.post_type = 'rz_listing' AND wp.post_status = 'publish' BY LIMIT ?,?
     *
     * @param  int $limit_start
     * @param  int $limit
     * @return array
     */
    public function getAllPostsRZListing($limit_start, $limit)
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT wp.id FROM `wp_posts` wp WHERE wp.post_type = 'rz_listing' AND wp.post_status = 'publish' ORDER BY wp.id ASC LIMIT ?,?"
            );
            $query->execute([$limit_start, $limit]);
            return $query->fetchAll();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Getting id & post_content FROM wp_posts WHERE wp.post_type = 'rz_listing' BY LIMIT ?,?
     *
     * @param  int $limit_start
     * @param  int $limit
     * @return array
     */
    public function getAllPostsContentRZListing($listing_type, $limit_start, $limit)
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT wp.id, wp.post_content FROM `wp_posts` wp LEFT JOIN `wp_postmeta` wppm ON wp.id = wppm.post_id  WHERE wp.post_type = 'rz_listing' AND  wppm.meta_value = ? ORDER BY wp.id DESC LIMIT ?,?"
            );
            $query->execute([$listing_type, $limit_start, $limit]);
            return $query->fetchAll();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }
    /**
     * Getting all meta value from wp_postmeta by post_id
     *
     * @param  string $post_id
     * @return array
     */
    public function getAllMetaByPost($post_id)
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT wp.meta_key, wp.meta_value FROM `wp_postmeta` wp WHERE wp.post_id = ? ORDER BY meta_key ASC"
            );
            $query->execute([$post_id]);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }
    /**
     * Getting all meta value from wp_postmeta by post_id & meta_key
     *
     * @param  string $post_id
     * @param  string $meta_key
     * @return array
     */
    public function getAllMetaByPostByMetakey($post_id, $meta_key)
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT wp.meta_value FROM `wp_postmeta` wp WHERE wp.post_id = ? AND wp.meta_key = ?"
            );
            $query->execute([$post_id, $meta_key]);
            return $query->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }
    /**
     * Searching for the records by property_id
     *
     * @param  string $table
     * @param  int $id
     * @return array
     */
    protected function searchForId($table, $id)
    {
        try {
            $query = $this->pdo->prepare("SELECT id FROM $table WHERE property_id = $id");
            $query->execute();
            return $query->fetchAll();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Checking for records in the rental table using the link
     *
     * @param  string $link
     * @return int
     */
    protected function checkLinkRental($link)
    {
        $query = $this->pdo->prepare("SELECT EXISTS (SELECT link FROM properties WHERE link = ?)");
        $query->execute([$link]);
        return $query->fetchColumn();
    }

    /**
     * Searching the records for the link
     *
     * @param  string $table
     * @param  string $link
     * @return int
     */
    protected function searchForLink($table, $link)
    {
        $query = $this->pdo->prepare("SELECT id FROM $table WHERE link = '$link'");
        $query->execute();
        return $query->fetchColumn();
    }
}
