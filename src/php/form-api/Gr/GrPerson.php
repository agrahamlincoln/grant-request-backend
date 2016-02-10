<?php

/**
 *  GrPerson Class
 *  ==============
 *
 *  Class modeling GrPerson entities
 *
 */

namespace FormAPI\Gr;
use \FormAPI\PDOFactory as PDOFactory;
use \FormAPI\LoggerFactory as LoggerFactory;
use \PDO as PDO;

class GrPerson extends \FormAPI\Person {

  /**
   *  @var Request ID - ID of the request
   */
  private $request_id;
  public $request_id_name = "Request ID";
  public function getRequestId() { return $this->request_id; }

  /**
   *  @var Person ID - table ID of person
   */
  private $person_id;
  public $person_id_name = "Person ID";
  public function getId() { if (isset($this->person_id)) { return $this->person_id; } else { return 0; } }
  public function setPersonId($person_id) { if ($person_id > 0) { $this->person_id = $person_id; } else { $this->person_id = 0; } }

  /**
   *  @var Person Email
   */
  private $email;
  public $email_name = "Email Address";
  public function getEmail() { if (isset($this->email)) { return $this->email; } else {return 0; }}
  public function setEmail($email) { $this->email = $email; }

  /**
   *  @var era_id
   */
  private $era_id;
  public $era_id_name = "eRA Commons ID";
  public function setEraId($era_id) {
    //era_id is TINYTEXT in DB (255chars max)
    if (strlen($era_id) > 20) {
      throw new Exception("eRA Commons ID must be less than 20 characters.");
    }
    else {
      $this->era_id = $era_id;
    }
  }
  public function getEraId() { if (isset($this->era_id)) { return $this->era_id; } else { return 0; }}
  /**
   *  @var availability - Will the PI be unavailable anytime within 3 weeks prior to submission?
   */
  private $availability;
  public $availability_name = "Availability";
  public function setAvailability($availability) { $this->availability = filter_var($availability, FILTER_VALIDATE_BOOLEAN); }
  public function getAvailability() { return $this->availability; }

  /**
   *  @var Project role
   */
  public $project_role;
  public $project_role_name = "Project Role";
  public function getRole() { if (isset($this->project_role)) { return $this->project_role; } else { return 0; }}

  /**
   *  @var effort
   */
  public $effort;
  public $effort_name = "Effort";
  public function getEffort() { if (isset($this->effort)) { return $this->effort; } else { return 0; }}

  /**
   *  @var annual fee
   */
  public $annual_fee;
  public $annual_fee_name = "Annual Fee";
  public function getFee() { if (isset($this->annual_fee)) { return $this->annual_fee; } else { return 0; }}

  public function __construct($req_id, $name) {
    parent::__construct($name);
    $this->request_id = $req_id;

    $numargs = func_num_args();
    switch ($numargs) {
      case 2:
        break;
      case 3:
        $this->setEmail(func_get_arg(2));
        break;
      case 4:
        $this->project_role = func_get_arg(2);
        $this->effort = func_get_arg(3);
        break;
      case 9:
        $this->setPersonId(func_get_arg(2));
        $this->setEraId(func_get_arg(3));
        $this->setAvailability(func_get_arg(4));
        $this->project_role = func_get_arg(5);
        $this->effort = func_get_arg(6);
        $this->setEmail(func_get_arg(7));
        $this->annual_fee = func_get_arg(8);
        break;
      default:
        throw new Exception("Invalid args provided");
        break;
    }

  }

  /**
   * Retrieves a person from the database by ID
   * @param int $ID ID number of the GrPerson
   * @return GrPerson|bool
   */
  public static function getById($id) {
    $conn = PDOFactory::getFactory()->getConnection();

    $selectPerson = $conn->prepare("SELECT * FROM gr_people
      WHERE person_id=:person_id");
    $selectPerson->bindValue(":person_id", $id);
    $selectPerson->execute();
    if ($row = $selectPerson->fetch(PDO::FETCH_ASSOC)) {
      $person = self::constructFromRow($row);
    } else {
      //No persons found
      $person = FALSE;
    }

    return $person;
  }

  public static function constructFromRow($row) {
    //Construct using the full-arg constructor
    $person = new self($row['request_id'],
      $row['name'],
      $row['person_id'],
      $row['era_id'],
      $row['availability'],
      $row['project_role'],
      $row['effort'],
      $row['email_address'],
      $row['annual_fee']);

    return $person;
  }

  /**
   * Updates the current object to match the record found in the database
   * if no record is found, nothing is changed
   */
  public function updateFromDB() {
    $conn = PDOFactory::getFactory()->getConnection();

    if ($matching_conditions = $this->build_where_matching_conditions()) {
      $where_clause = array_shift($matching_conditions);
      $querystring = "SELECT * FROM gr_people WHERE " . $where_clause;
      $select_gr_people = $conn->prepare($querystring);

      //Because the query was built dynamically, it is imperative that we verify it ran successfully
      if (!$select_gr_people->execute($matching_conditions)) {
        throw new Exception($select_gr_people->errorInfo()[2]);
      } else {
        if ($select_gr_people->rowCount() > 0) {
          $row = $select_gr_people->fetch(PDO::FETCH_ASSOC);
          $this->person_id = $row['person_id'];
          $this->name = $row['name'];
          $this->era_id = $row['era_id'];
          $this->availability = $row['availability'];
          $this->project_role = $row['project_role'];
          $this->effort = $row['effort'];
          $this->email_address = $row['email_address'];
          $this->annual_fee = $row['annual_fee'];
        }
      }
    }
  }

  /**
   * Attempts to insert or update this record in the database
   * @return GrPerson
   */
  public function add_get() {
    $conn = PDOFactory::getFactory()->getConnection();
    $logger = LoggerFactory::getFactory()->getLogger();

    //Check if person exists
    if ($this->exists()) {
      //Update record!
      $logger->debug("Person already exists; Updating!");
      $this->updateDB();
      return $this;

    } else {
      //Insert new record!
      if ($fields = $this->toArray()) {

        //Create individual arrays for each key and value to insert for this record
        $cols = implode(',',array_keys($fields));

        //Create the Values List
        $values = array();
        foreach ($fields as $column => $value) {
          $values[] = ':' . $column;
        }
        $vals = implode(', ',$values);

        $logger->debug('Fields Added to Person: ' . $cols);
        $logger->debug('vals: ' . $vals);

        //Prepare the sql statement
        $insert_gr_people = $conn->prepare('INSERT INTO gr_people (' . $cols . ') VALUES (' . $vals . ')');

        //Bind the parameters to the values
        foreach ($fields as $variable => $value) {
          $param[] = ':' . $variable;
          $param[] = $value;
          call_user_func_array(array($insert_gr_people, 'bindValue'), $param);
          $logger->debug('BindValue: ', $param);

          //clear the values of $param
          $param = null;
        }

        //Because this query is dynamically created, we need to verify that it completed
        if (!$insert_gr_people->execute()) {
          throw new Exception($insert_gr_people->errorInfo()[2]);
        } else {
          if ($insert_gr_people->rowCount() != 1) {
            throw new Exception("More or less than 1 rows were affected when trying to insert a new GrPerson");
          } else {
            $this->person_id = $conn->lastInsertId();

            $qry = $conn->prepare("SELECT * FROM gr_people WHERE person_id=" . $this->person_id);
            $qry->execute();
            $logger->debug('Inserted Record: ', $qry->fetch(PDO::FETCH_ASSOC));


            return $this;
          }
        }
      }
    }
  }

  /**
   *  Constructs an array with only the fields of GrPerson that are used
   * @param bool $allow_emptyFields Optional parameter to allow setting empty fields in the database
   * @return array
   */
  public function toArray($allow_emptyfields = FALSE) {
    //construct array of data to insert
    $fields = Array();

    if ($this->getRequestId() > 0) {
      $fields["request_id"] = $this->getRequestId();
    } else {
      throw new Exception("Request ID is invalid.");
    }
    if ($this->name && $this->name != '') {
      $fields["name"] = $this->name;
    } else {
      //echo "Name:[" . $this->name . "]";
      throw new Exception("Name is invalid.");
    }

    if ($this->email) {
      $fields["email_address"] = $this->email;
    } elseif ($allow_emptyfields) { $fields["email_address"] = NULL; }

    if ($this->era_id) {
      $fields["era_id"] = $this->era_id;
    } elseif ($allow_emptyfields) { $fields["era_id"] = NULL; }

    if ($this->availability > 0) {
      $fields["availability"] = $this->availability;
    } elseif ($allow_emptyfields) {
      $fields["availability"] = 1;
    }

    if ($this->project_role) {
      $fields["project_role"] = $this->project_role;
    } elseif ($allow_emptyfields) { $fields["project_role"] = NULL; }

    if ($this->effort > 0) {
      $fields["effort"] = $this->effort;
    } elseif ($allow_emptyfields) { $fields["effort"] = NULL; }

    if ($this->annual_fee > 0) {
      $fields["annual_fee"] = $this->annual_fee;
    } elseif ($allow_emptyfields) { $fields["annual_fee"] = NULL; }

    return $fields;
  }

  /**
   * Updates the database with the current object
   */
  public function updateDB() {
    $conn = PDOFactory::getFactory()->getConnection();

    if ($this->exists()) {

      $set_clause_array = Array();
      foreach ($this->toArray() as $col => $val) {
        $set_clause_array[] = $col . '=:set' . $col;
      }
      $set_clause = implode(', ', $set_clause_array);

      if (count($set_clause_array) > 0) {
        if ($matching_conditions = $this->build_where_matching_conditions()) {
          $where_clause = array_shift($matching_conditions);
          $querystring = 'UPDATE gr_people SET ' . $set_clause . ' WHERE ' . $where_clause;
          $update_gr_people = $conn->prepare($querystring);

          //Bind the setclause variables
          foreach ($this->toArray() as $col => $val) {
            $value[] = ":set" . $col;
            $value[] = $val;
            call_user_func_array(array($update_gr_people, 'bindValue'), $value);
            //clear the value of $value
            $value = null;
          }

          //Iterate through the matching conditions and bind to the prepared statement
          foreach ($matching_conditions as $variable => $value) {
            $param[] = $variable;
            $param[] = $value;
            call_user_func_array(array($update_gr_people, 'bindValue'), $param);

            //clear the values of $param
            $param = null;
          }

          //because the query is dynamically created, it is imperative that we verify that it worked
          if (!$update_gr_people->execute()) {
            throw new Exception($update_gr_people->errorInfo()[2]);
          }
        }
      }
    }
  }

  /**
   * Verifies that the current instance of this class exists in the database
   * @return bool
   */
  public function exists() {
    $conn = PDOFactory::getFactory()->getConnection();
    $logger = LoggerFactory::getFactory()->getLogger();

    if ($matching_conditions = $this->build_where_matching_conditions()) {
      $where_clause = array_shift($matching_conditions);
      $querystring = 'SELECT * FROM gr_people WHERE ' . $where_clause;
      $select_gr_people = $conn->prepare($querystring);

      //Iterate through the matching conditions and bind to the prepared statement
      foreach ($matching_conditions as $variable => $value) {
        $param[] = $variable;
        $param[] = &$value;
        call_user_func_array(array($select_gr_people, 'bindValue'), $param);

        //clear the values of $param
        $param = null;
      }

      //because the query is dynamically created, it is imperative that we verify that it worked
      if (!$select_gr_people->execute()) {
        throw new Exception($update_gr_people->errorInfo()[2]);
      } else {
        if ($select_gr_people->rowCount() > 1) {
          //Multiple matches.. this should never happen
          return FALSE;
        } else if ($select_gr_people->rowCount() === 1) {
          $person = $select_gr_people->fetch(PDO::FETCH_ASSOC);
          $this->person_id = $person['person_id'];
          $logger->debug("1 Found person in database. Person ID (" . $person['person_id'] . ")Matched on the following criteria", $matching_conditions);
          return TRUE;
        } else {
          // 0 matches
          return FALSE;
        }
      }
    }
  }

  /**
   * Constructs a set of WHERE Clauses (i.e. 'column1=:column1 AND column2=:column2') and returns the values associated with each.
   * The return value should be shifted to return the clause, and then the remaining entries in the array are the data values
   * @return array
   */
  public function build_where_matching_conditions() {
    $data = Array();
    $whereQuery = Array();
    $whereData = Array();
    //Evaluate current object's fields and use whats available to match
    //Maximum use: name, email_address, and project_role
    if ($this->request_id) {
      $criteria['column'] = 'request_id';
      $criteria['data'] = $this->request_id;
      $data[] = $criteria;
    } else {
      throw new Exception("Person must have a Request ID.");
    }
    if ($this->person_id) {
      $criteria['column'] = 'person_id';
      $criteria['data'] = $this->person_id;
      $data[] = $criteria;
    } else {
      if ($this->getEmail()) {
        $criteria['column'] = 'email_address';
        $criteria['data'] = $this->getEmail();
        $data[] = $criteria;
      }
      if ($this->getName()) {
        $criteria['column'] = 'name';
        $criteria['data'] = $this->getName();
        $data[] = $criteria;
      }
      if ($this->getRole()) {
        $criteria['column'] = 'project_role';
        $criteria['data'] = $this->getRole();
        $data[] = $criteria;
      }
    }

    foreach($data as $criteria) {
      $whereQuery[] = $criteria['column'] . '=:' . $criteria['column'];
      $whereData[':' . $criteria['column']] = $criteria['data'];
    }

    //Push query string to beginning of the array
    array_unshift($whereData, implode(' AND ', $whereQuery));

    return $whereData;
  }
}

?>