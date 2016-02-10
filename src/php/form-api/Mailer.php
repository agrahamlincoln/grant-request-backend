<?php
namespace FormAPI;
use \PDO as PDO;
/**
 * Mailer Class
 * ============
 *
 * PHP Class that handles Email sending
 */

class Mailer {
  private $requestType;
  private $recipients;
  private $headers;

  public function __construct($requestType) {
    //define the requestType
    $this->requestType = $requestType;

    //set up email headers
    $from = "Forms@uemf.org";
    $replyTo = "support@uemf.org";
    $this->headers = "From: UEMF Forms <" . $from . ">\r\n"
                   . "Reply-To: UEMF-IT Support <" . $replyTo . ">\r\n"
                   . "Content-type:text/html; charset=UTF-8";

    //fetch recipients from database
    $this->recipients = $this->fetchRecipients();

  }

  private function fetchRecipients() {
    $recipients = array();
    //Get a database connection
    $conn = PDOFactory::getFactory()->getConnection();

    //Query the appEmail database for all recipients of this type
    $selectRecipients = $conn->prepare("SELECT * FROM appEmail WHERE requestType = :requestType AND isActive = '1'");
    $selectRecipients->bindParam(":requestType", $this->requestType);

    $selectRecipients->execute();

    if ($selectRecipients->rowCount() > 0) {
      while($row = $selectRecipients->fetch(PDO::FETCH_ASSOC)) {
        $recipients[] = $row['emailAddress'];
      }
    }

    return $recipients;
  }

  public function send($subject, $body) {
    if (count($this->recipients) > 0) {
      $to = implode(', ', $this->recipients);
      $success = mail($to, $subject, $body, $this->headers);
      return $success;
    } else {
      return false;
    }
  }
}