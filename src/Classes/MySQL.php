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
            $dbname = env('MYSQL_WPDB', 'wp_tempdbookindev');
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
     * Getting all records by is_deleted = 0 AND post_id is NULL
     *
     * @param  string $table
     * @return array
     */
    public function getAllNewRecords($table)
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT * FROM $table WHERE (is_deleted = 0 AND post_id is NULL) OR (is_deleted IS NULL AND post_id is NULL) ORDER BY id DESC"
            );
            $query->execute();
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
    public function getAvailability($property_id)
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT * FROM `availability` WHERE property_id = ?"
            );
            $query->execute([$property_id]);
            return $query->fetchAll();
        } catch (\Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
     * Searching for the records by ID
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
