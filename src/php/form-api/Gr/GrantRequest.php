<?php

/*
 *  Grants Request Module
 *  =====================
 *
 *  Main class for Grant Request
 * This class stores the data model and basic information about the request. All functions in here pertain to high-level data operations interfacing the database.
 *
 */

namespace FormAPI\Gr;
use \FormAPI\PDOFactory as PDOFactory;
use \FormAPI\LoggerFactory as LoggerFactory;
use \PDO as PDO;

use \FormAPI\Requester as Requester;

/**
  * Main class for Grant Request
  * Models the entire request at a top-level
  */
class GrantRequest {

  private $id;            // ID Number of the request
  private $model;         // Array Model of the request
  private static $logger;

  public function __construct($data) {
    if (gettype($data) === "string" || gettype($data) === "integer") {
      //parameter passed was the request ID
      $this->id = $data;
    } elseif (gettype($data) === "object" || gettype($data) === "array") {
      //parameter passed was the model
      $this->model = $data;
    } else {
      throw new Exception("Invalid parameter");
    }

    //Retrieve the logger object from the factory
    self::$logger = LoggerFactory::getFactory()->getLogger();
  }

  /**
   * Returns the model to the user and fetches it if neccesary
   */
  public function getModel() {

    if (!$this->model) {
      if (!$this->fetchModel()) {
        //error fetching model from database
        self::$logger->error("Attempted to retrieve model from the database and failed");
        return 0;
      }
    }

    return $this->model;
  }

  /**
   * Retrieves the model from the database
   */
  private function fetchModel() {
    self::$logger->debug("==START MODEL FETCH==", array('id' => $this->id));
    $conn = PDOFactory::getFactory()->getConnection();

    $select_details = $conn->prepare("SELECT *
      FROM gr_details
      WHERE request_id=:request_id");
    $select_details->bindParam(':request_id', $this->id);

    $select_details->execute();

    if ($select_details->rowCount() === 1) {
      $row = $select_details->fetch(PDO::FETCH_ASSOC);
      $fundOpportunity = array(
        "sponsor" => $row['sponsor_name'],
        "details" => $row['funding_opportunity'],
        "website" => $row['website'],
        "fundMech" => $row['funding_mechanism'],
        "dueDate" => $this->convertDateFromSQL($row['due_date'])
      );

      $proposal = array(
        "title" => $row['proposal_title'],
        "shortTitle" => $row['short_title'],
        "startDate" => $this->convertDateFromSQL($row['start_date']),
        "endDate" => $this->convertDateFromSQL($row['end_date'])
      );

      $special = array(
        "humans" => filter_var($row['subj_humans'], FILTER_VALIDATE_BOOLEAN),
        "clinical" => filter_var($row['human_clinical'], FILTER_VALIDATE_BOOLEAN),
        "phase3" => filter_var($row['human_p3_clinical'], FILTER_VALIDATE_BOOLEAN),
        "vertebrate" => filter_var($row['subj_vertebrates'], FILTER_VALIDATE_BOOLEAN),
        "agents" => filter_var($row['subj_agents'], FILTER_VALIDATE_BOOLEAN),
        "stemcells" => filter_var($row['subj_stemcells'], FILTER_VALIDATE_BOOLEAN)
      );

      $comments = $row['comments'];
    } else {
      $logger->error("Could not find an entry in the gr_details table for request ID: " + $this->id);
      return 0;
    }

    $model = array(
      "fundOpportunity" => $fundOpportunity,
      "proposal" => $proposal,
      "special" => $special,
      "comments" => $comments
    );
    $pi = $this->getPi();
    if ($pi) {
      $model["principalInvestigator"] = array(
        "name" => $pi->getName(),
        "email" => $pi->getEmail(),
        "fedId" => $pi->getEraId(),
        "vacation" => $pi->getAvailability()
      );
      self::$logger->debug("Successfully retrieved the principal investigator from the database", $model["principalInvestigator"]);
    }
    $personnel = $this->getPersonnel();
    if ($personnel) {
      $model["personnel"] = $personnel;
      self::$logger->debug("Successfully retrieved the personnel from the database", $personnel);
    }
    $consultants = $this->getConsultants();
    if ($consultants) {
      $model["consultants"] = $consultants;
      self::$logger->debug("Successfully retrieved the consultants from the database", $consultants);
    } else {
      self::$logger->debug("No Consultants were listed");
    }
    $subawards = $this->getSubawards();
    if ($subawards) {
      $model["subawards"] = $subawards;
      self::$logger->debug("Successfully retrieved the subawards from the database", $subawards);
    } else {
      self::$logger->debug("No Subaward Institutions were listed");
    }

    //cast model to StdObj type and save it
    $this->model = $model;
    self::$logger->debug("==END MODEL FETCH==", $model);

    return 1;
  }

  /**
   * Saves a full data model to the database
   */
  public function saveModel() {
    self::$logger->debug("==BEGIN MODEL SAVE==", (array) $this->model);

    $result = array();
    $result['success'] = true;

    $model = $this->model; //assign it here for short-hand
    $conn = PDOFactory::getFactory()->getConnection();

    //Federal ID is Optional
    if ($model->principalInvestigator->fedId) {
      $PiFedId = $model->principalInvestigator->fedId;
    }

    $reqID = 0; //Initialize the ID for future use

    if (Requester::import($model->principalInvestigator)) {
      self::$logger->debug("Successfully saved Requester");
    }

    $requester = Requester::getByEmail($model->principalInvestigator->email);

    /**
     * Handle the request
     * - Insert a new request into the database
     */
    $insertRequest = $conn->prepare("INSERT INTO request (type_id, timestamp, method, requester) VALUES ('1', now(), 'SAVE', :requester)");
    $requesterID = $requester->requester_id;
    $insertRequest->bindParam(":requester", $requesterID);
    $insertRequest->execute();

    if ($insertRequest->rowCount() == 0) {
      self::$logger->critical("Failed to insert request");
      $result['success'] = false;
      $result['request_id'] = -1;
    } else {
      $reqID = $conn->lastInsertId();
      self::$logger->info("Inserted Request with ID " . $reqID);

      //Now that we have a valid ID, set it to the response array
      $result['request_id'] = $reqID;

      /**
       * Process Grants Request Details
       * - Move through the model, evaluate whether is defined or not
       * - Create an array of all columns and values used
       * - Split the columns and values, construct a sql statement
       * - Insert all the provided data into the Gr_Details table
       */
      self::$logger->debug("Processing request details");
      $fields = array();
      $fields['request_id'] = $reqID;

      //Funding Opportunity Section
      if ($model->fundOpportunity->sponsor) {
        $fields['sponsor_name'] = $model->fundOpportunity->sponsor;
      }
      if ($model->fundOpportunity->details) {
        $fields["funding_opportunity"] = $model->fundOpportunity->details;
      }
      if ($model->fundOpportunity->website) {
        $fields["website"] = $model->fundOpportunity->website;
      }
      if ($model->fundOpportunity->fundMech) {
        $fields["funding_mechanism"] = $model->fundOpportunity->fundMech;
      }
      if ($model->fundOpportunity->dueDate) {
        $dueDate = $this->convertDateToSQL($model->fundOpportunity->dueDate);
        if ($dueDate == '1969-12-31') {
          self::$logger->warning("Due Date could not be parsed, failing back to 12/31/1969");
        }
        $fields["due_date"] = $dueDate;
      }

      //Proposal Section
      if ($model->proposal->title) {
        $fields["proposal_title"] = $model->proposal->title;
      } else {
        //Critical - The proposal title is a required field. We must break out.
        self::$logger->error("Proposal Title is missing");
        header("X-PHP-Response-Code: 400", true, 400);
        $result['success'] = false;
        return $result;
      }
      if ($model->proposal->shortTitle) {
        $fields["short_title"] = $model->proposal->shortTitle;
      } else {
        //Critical - The short title is a required field. We must break out.
        self::$logger->error("Short Title is missing");
        header("X-PHP-Response-Code: 400", true, 400);
        $result['success'] = false;
        return $result;
      }
      if ($model->proposal->startDate) {
        $startDate = $this->convertDateToSQL($model->proposal->startDate);
        if ($startDate == '1969-12-31') {
          self::$logger->warning("Start Date could not be parsed, failing back to 12/31/1969");
        }
        $fields["start_date"] = $startDate;
      }
      if ($model->proposal->endDate) {
        $endDate = $this->convertDateToSQL($model->proposal->endDate);
        if ($endDate == '1969-12-31') {
          self::$logger->warning("End Date could not be parsed, failing back to 12/31/1969");
        }
        $fields["end_date"] = $endDate;
      }

      //Special Considerations Section
      if (filter_var($model->special->humans, FILTER_VALIDATE_BOOLEAN)) {
        $fields["subj_humans"] = 1;
      }
      if (filter_var($model->special->clinical, FILTER_VALIDATE_BOOLEAN)) {
        $fields["human_clinical"] = 1;
      }
      if (filter_var($model->special->phase3, FILTER_VALIDATE_BOOLEAN)) {
        $fields["human_p3_clinical"] = 1;
      }
      if (filter_var($model->special->vertebrate, FILTER_VALIDATE_BOOLEAN)) {
        $fields["subj_vertebrates"] = 1;
      }
      if (filter_var($model->special->agents, FILTER_VALIDATE_BOOLEAN)) {
        $fields["subj_agents"] = 1;
      }
      if (filter_var($model->special->stemcells, FILTER_VALIDATE_BOOLEAN)) {
        $fields["subj_stemcells"] = 1;
      }

      //Comments section
      if ($model->comments) {
        $fields["comments"] = $model->comments;
      }

      //Join the columns seperated by ',' for SQL syntax
      $cols = implode(',',array_keys($fields));

      $params = array();
      foreach ($fields as $col => $value) {
        $params[':' . $col] = $value;
      }
      $values_clause = implode(',', array_keys($params));

      $insertQueryString = 'INSERT INTO gr_details (' . $cols . ') VALUES (' . $values_clause . ')';
      $insertDetails = $conn->prepare($insertQueryString);

      $insertDetails->execute($params);

      if ($insertDetails->rowCount() == 1) {
        self::$logger->debug("Successfully added request details");
      } else {
        //Critical error - we must break out here.
        self::$logger->error("Failed to insert request details.");
        self::$logger->error("Mysql error: " . $mysqli_error);
        header("X-PHP-Response-Code: 500", true, 500);
        $result['success'] = false;
        return $result;
      }

      /**
       * Process the Personnel Section
       * - Createa GrPerson objects for each personnel
       * - Add the person to the gr_person
       * - Add the person to the gr_personnel entity bridge table
       */
      self::$logger->debug("Processing Personnel Section");
      foreach ($model->personnel as $personnel) {
        if ($personnel->name) {
          $person = new GrPerson($reqID, $personnel->name, $personnel->role, $personnel->effort);
          self::$logger->debug("Processing Personnel: " . json_encode($personnel));

          //Insert into DB
          $person = $person->add_get();

          if ($person->getId()) {
            self::$logger->info("Successfully added person.", $person->toArray());

            //Add the personnel to the gr_personnel table which identifies what type of person this is
            if ($this->addPersonnel($reqID, $person->getId(), 'personnel')) {
              self::$logger->info("Successfully added to personnel");
            } else {
              self::$logger->error("Failed to add " . $personnel->name . " to personnel");
              self::$logger->debug("Person ID: " . $person->getId());
            }
          } else {
            self::$logger->error("Failed to add " , $personnel->name);
          }
        }
      }

      /**
       * Update the Principal Investigator's Information
       * - Query the database for project_role = 'Principal Investigator'
       * - Create a GrPerson object for the PI
       * - Update the Email & Fed ID if available
       * - Write new changes back to the database
       */
      self::$logger->debug("Updating Principal Investigator");

      //Get the PI - Search by project_role
      $selectPI = $conn->prepare("SELECT * FROM gr_people WHERE request_id=:request_id and project_role = 'Principal Investigator'");
      $selectPI->bindParam(":request_id", $reqID);
      $selectPI->execute();

      if ($selectPI->rowCount() > 0) {
        //Create an object for this person
        $row = $selectPI->fetch(PDO::FETCH_ASSOC);
        $PI = new GrPerson($row['request_id'], $row['name'], $row['email_address']);
        if ($row['era_id']) { $PI->setEraId($row['era_id']); }
        $PI->updateFromDB();

        //Update the email & Federal ID, then write it to the database
        if ($PiFedId) { $PI->setEraId($PiFedId); }
        $PI->setEmail($model->principalInvestigator->email);
        $PI->updateDB();
      } else {
        self::$logger->error("Could not find Principal Investigator");
      }

      /**
       * Process the Consultants Section
       * - Create GrPerson object for each consultant
       * - Add the person to gr_person
       * - Add the person to gr_personnel
       */
      self::$logger->debug("Processing Consultant Section");
      $num_consults = 0;
      foreach($model->consultants as $consult) {
        if ($consult->name) {
          //We can assume that all of these will be at least emptystring
          $person = new GrPerson($reqID, $consult->name, $consult->email);
          $person->annual_fee = $consult->fee;
          self::$logger->debug("Processing Consultant: " . $consult->name);

          //Insert into DB
          $person = $person->add_get();
          if ($person->getId()) {
            self::$logger->debug("Successfully added person");

            //Add the person to the gr_personnel table which identifies what type of person this is.
            if ($this->addPersonnel($reqID, $person->getId(), 'consultant')) {
              self::$logger->debug("Successfully added to consultants");
            } else {
              self::$logger->error("Failed to add " . $consult->name . " to consultants");
              self::$logger->debug("Person ID: " . $person->getId());
            }
          } else {
            self::$logger->error("Failed to add " , $consult->name);
          }
          $num_consults++;
        }
      }
      if ($num_consults == 0) {
        self::$logger->warning("You did not specify any consultants");
      }

      /**
       * Process Subawards Section
       * - Create a GrPerson for both associated persons
       * - Add each associated persons to gr_person
       * - Add subaward to gr_subawards with persons attached
       */
      self::$logger->debug("Processing Subaward Section");
      $num_subawards = 0;
      foreach($model->subawards as $subaward) {
        if (!($subaward->name)) {
          self::$logger->info("Subaward has no name. This was skipped", (array)$subaward);
        } else {

          //Get the Subaward Name
          $institution_name = $subaward->name;
          $queryData = array(
            'request_id' => $reqID,
            'institution_name' => $institution_name
          );

          //Get the Principal Investigator
          if (!($subaward->principalInvestigator->name)) {
            self::$logger->info("Subaward " . $subaward->name . " had no principal investigator specified.");
          } else {
            $PI = new GrPerson($reqID, $subaward->principalInvestigator->name, $subaward->principalInvestigator->email);
            $PI = $PI->add_get();
            $queryData['primaryinv_id'] = $PI->getId();
          }

          //Get the Grants Administrator
          if (!($subaward->grantAdmin->name)) {
            self::$logger->info("Subaward " . $subaward->name . " had no grants administrator specified.");
          } else {
            $grAdmin = new GrPerson($reqID, $subaward->grantAdmin->name, $subaward->grantAdmin->email);
            $grAdmin = $grAdmin->add_get();
            $queryData['gradmin_id'] = $grAdmin->getId();
          }

          //Construct query from queryData
          $query_keys = array_keys($queryData);
          $query_columns = "(" . implode(', ', $query_keys) . ")";
          $query_values = "(:" . implode(', :', $query_keys) . ")";
          $query = "INSERT INTO gr_subawards $query_columns VALUES $query_values";

          //PDO Prepare & Execute Query
          $insertGrSubawards = $conn->prepare($query);
          $insertGrSubawards->execute($queryData);

          if ($insertGrSubawards->rowCount() == 1) {
            self::$logger->debug("Inserted Subaward");
          } else {
            self::$logger->error("Failed to insert Subaward: " . $subaward->name);
          }
          $num_subawards++;
        }
      }
      if ($num_subawards == 0) {
        self::$logger->warning("You did not specify any subawards");
      }

      self::$logger->debug("==END MODEL SAVE==");

      return $result;
    }
  }

  private function addPersonnel($reqID, $personID, $personnelType) {
    $conn = PDOFactory::getFactory()->getConnection();

    $insertGrPersonnel = $conn->prepare("INSERT INTO gr_personnel (request_id, person_id, type) VALUES (:request_id, :person_id, :type)");

    $params = array();
    $params[':request_id'] = $reqID;
    $params[':person_id'] = $personID;
    $params[':type'] = $personnelType;

    $insertGrPersonnel->execute($params);

    if ($insertGrPersonnel->rowCount() == 1) {
      return TRUE;
    } else {
      return FALSE;
    }
  }

  /**
   * Converts a mysql date object in yyyy-mm-dd format into mm/dd/yyyy format
   */
  private function convertDateFromSQL($date) {
    $arrayDate = date_parse($date);
    if ($arrayDate['error_count']) {
      $stringDate = "[Unspecified]";
    } else {
      $stringDate = $arrayDate['month'] . '/' . $arrayDate['day'] . '/' . $arrayDate['year'];
    }

    return $stringDate;
  }

  /**
   * Converts a non-sql date object from mm/dd/yyyy format to yyyy-mm-dd
   */
  private function convertDateToSQL($date) {
    return date('Y-m-d', strtotime($date));
  }

  /**
   * Retrieves an array of the principal investigator in format for the model
   */
  private function getPi() {
    $conn = PDOFactory::getFactory()->getConnection();

    $select_pi = $conn->prepare("SELECT *
      FROM gr_people
      WHERE request_id=:request_id and project_role=:project_role");
    $role = "Principal Investigator";

    $params = array();
    $params[':request_id'] = $this->id;
    $params[':project_role'] = $role;

    $select_pi->execute($params);

    if ($select_pi->rowCount() > 0) {
      $row = $select_pi->fetch(PDO::FETCH_ASSOC);
      $person = GrPerson::constructFromRow($row);
    } else {
      $person = 0;
    }

    return $person;
  }

  /**
   * Retrieves an array of the personnel in format for the model
   */
  private function getPersonnel() {
    $conn = PDOFactory::getFactory()->getConnection();

    $select_personnel = $conn->prepare("SELECT gr_people.* FROM gr_personnel INNER JOIN gr_people ON gr_people.person_id = gr_personnel.person_id WHERE gr_personnel.request_id=:request_id  AND gr_personnel.type='personnel'");
    $select_personnel->bindParam(":request_id", $this->id);

    $select_personnel->execute();

    //Construct array of personnel
    $personnel = Array();
    if ($select_personnel->rowCount() > 0) {
      while ($row = $select_personnel->fetch(PDO::FETCH_ASSOC)) {
        //Create a person object for this row
        $person = GrPerson::constructFromRow($row);

        //Create the model structure for this personnel
        $personnel[] = array(
          "name" => $person->getName(),
          "role" => $person->getRole(),
          "effort" => $person->getEffort()
        );
      }
    } else {
      $personnel = 0;
    }
    return $personnel;
  }

  /**
   * Retrieves an array of the consultants in format for the model
   */
  private function getConsultants() {
    $conn = PDOFactory::getFactory()->getConnection();

    $select_consultants = $conn->prepare("SELECT gr_people.* FROM gr_personnel 
      INNER JOIN gr_people ON gr_people.person_id = gr_personnel.person_id 
      WHERE gr_personnel.request_id=:request_id AND gr_personnel.type='consultant'");
    $select_consultants->bindParam(":request_id", $this->id);

    $select_consultants->execute();

    //Construct array of consultants
    $consultants = Array();
    if ($select_consultants->rowCount() > 0) {
      while ($row = $select_consultants->fetch(PDO::FETCH_ASSOC)) {
        //Create a person object for this row
        $consultant = GrPerson::constructFromRow($row);

        //Create the model structure for this consultant
        $consultants[] = array(
          "name" => $consultant->getName(),
          "email" => $consultant->getEmail(),
          "fee" => $consultant->getFee()
        );
      }
    } else {
      return 0;
    }
    return $consultants;
  }

  /**
   * Retrieves an array of the subawards in format for the model
   */
  private function getSubawards() {
    $conn = PDOFactory::getFactory()->getConnection();

    $select_subawards = $conn->prepare("SELECT * FROM gr_subawards WHERE request_id = :request_id");
    $select_subawards->bindParam(":request_id", $this->id);

    $select_subawards->execute();

    //Construct array of subawards
    $subawards = Array();
    if ($select_subawards->rowCount() > 0) {
      while ($row = $select_subawards->fetch(PDO::FETCH_ASSOC)) {
        $subaward = array();

        $subaward['name'] = $row['institution_name'];

        $pi = GrPerson::getById($row['primaryinv_id']);
        if ($pi) {
          $subaward['principalInvestigator'] = array(
            "name" => $pi->getName(),
            "email" => $pi->getEmail()
          );
        }

        $admin = GrPerson::getById($row['gradmin_id']);
        if ($admin) {
          $subaward['grantAdmin'] = array(
            "name" => $admin->getName(),
            "email" => $admin->getEmail()
          );
        }

        $subawards[] = $subaward;
      }
    } else {
      return 0;
    }

    return $subawards;
  }

}

?>