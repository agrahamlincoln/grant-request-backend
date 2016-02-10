<?php

/**
 * Repository Pattern Representation of a Requester object
 */

namespace FormAPI\Repository;
use \PDO;
use \FormAPI\PDOFactory;
use \FormAPI\LoggerFactory;
use \FormAPI\Requester;

/**
 * Models an entity from table requester
 */
class RequesterRepository implements iRepository
{
  private $conn;
  private $logger;

  public function __construct(PDO $connection = null) {
    $this->conn = $connection;
    if ($this->conn === null) {
      $this->conn = PDOFactory::getFactory()->getConnection();
    }

    $this->logger = LoggerFactory::getFactory()->getLogger();
  }

  /**
   * Searches the database for a requester with the specified email.
   * @param string $email Email Address of a requester
   * @return Requester|null
   */
  public function fetch($email) {
    $this->logger->debug('Searching database for `requester` "' . $email . '"');
    $statement = $this->conn->prepare('SELECT * FROM requester WHERE email_address=:email');
    $statement->bindValue(':email', $email);

    $statement->setFetchMode(PDO::FETCH_CLASS, '\FormAPI\Requester');
    $statement->execute();
    $columnCount = $statement->columnCount();
    if ($columnCount >= 1) {
      // 1 OR MORE RESULT

      $requester = $statement->fetch();
    } else {
      // NOTHING FOUND
      $requester = null;
    }
    return $requester;
  }

  /**
   * Inserts a record if it does not already exist, otherwise updates a matching record
   * @param Requester $requester a Requester object to save to the database
   * @return boolean
   */
  public function save($obj)
  {
    $returnValue = false;
    $this->logger->debug('Saving requester.', (array)$obj);
    if ($this->exists($obj)) {
      $returnValue = filter_var($this->update($obj), FILTER_VALIDATE_BOOLEAN);
    } else {
      $returnValue = filter_var($this->insert($obj), FILTER_VALIDATE_BOOLEAN);
    }
    return $returnValue;
  }

  /**
   * Verifies existence of a record in the database. This method will search by email_address which should be unique in this table.
   * @param Requester $requester a Requester object to search the database for
   * @return boolean
   */
  private function exists(Requester $requester) {
    $statement = $this->conn->prepare('SELECT COUNT(*) FROM requester WHERE email_address=:email');
    $statement->bindvalue(':email', $requester->email_address);
    $statement->execute();

    $count = $statement->fetch()[0];
    //convert to boolean
    return filter_var($count, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * Updates a record in the database
   * @param Reqester $requester a Requester object to update in the database
   * @return boolean
   */
  private function update(Requester $requester) {
    $returnValue = false;
    $databaseRecord = $this->fetch($requester->email_address);
    if ($databaseRecord) {
      //compare the two records.
      if ($databaseRecord === $requester) {
        //Objects are EXACTLY the same... do nothing
        $returnValue = true;
        $this->logger->debug('RequesterRepository->update(): New record matches current record.');
      } else {
        $old = (array)$databaseRecord;
        $new = (array)$requester;
        $diff = array_diff_assoc($new, $old);

        //CANNOT UPDATE requester_id
        if (array_key_exists('requester_id', $diff))
          unset($diff['requester_id']);

        //If there are any columns to update
        if (count($diff) > 0) {
          //create the SET CLAUSE
          $setvals = array();
          foreach ($diff as $col => $value) {
            $setvals[] = $col . "=" . ":" . $col;
          }
          $setClause = implode(', ', $setvals);

          $statement = $this->conn->prepare('UPDATE requester SET (' . $setClause . ') WHERE requester_id=:requester_id');

          $diff['requester_id'] = $databaseRecord->requester_id;
          $statement->execute($diff);
          $this->logger->debug('RequesterRepository->update(): Updated record', $diff);
        } else {
          $this->logger->debug('RequesterRepository->update(): No columns to update.');
        }

        $returnValue = true;
      }
    }

    return $returnValue;
  }

  /**
   * Inserts a requester object into the database
   * @param Requester $requester a Requester object to insert into database
   * @return Requester|false
   */
  private function insert(Requester $requester) {
    $returnValue = false; //Failed by default

    $statement = $this->conn->prepare('INSERT INTO requester (name, email_address, ip_address) VALUES (:name, :email, :ip)');

    $data = array(
      ':name' => $requester->name,
      ':email' => $requester->email_address,
      ':ip' => $requester->ip_address
    );

    $statement->execute($data);

    //Verify the record was inserted correctly
    if ($statement->rowCount() === 1) {
      // update $requester with new ID
      $requester->requester_id = $this->conn->lastInsertId();
      $returnValue = $requester;
      $this->logger->debug('RequesterRepository->insert(): Inserted new Requester', (array)$requester);
    }
    return $returnValue;
  }

  public function delete($obj) {
    $returnValue = false;
    if ($this->exists($obj)) {
      $statement = $this->conn->prepare('DELETE FROM requester WHERE requester_id=:requester_id');
      $statement->bindValue(':requester_id', $obj->requester_id);
      $returnValue = $statement->execute();
      $this->logger->debug('RequesterRepository->delete(): Deleted Requester', (array)$obj);
    }
    return $returnValue;
  }
}

?>