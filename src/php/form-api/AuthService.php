<?php
// Auth.php
// All code related to user authentication and handshaking is done here.
namespace FormAPI;
use \PDO as PDO;

class AuthService {

  /**
   *  Fetches the row from the database that corresponds to the key
   *  @param string $key The JWT token to search requesters for
   *  @return array
   */
  public static function fetch($key) {
    $db = PDOFactory::getFactory()->getConnection();

    $sel_requester = $db->prepare("SELECT * FROM requester where token = :token");
    $sel_requester->bindParam(":token", $key);
    $sel_requester->execute();
    return $sel_requester->fetch(PDO::FETCH_ASSOC);
  }

  /**
   *  Saves the key to the database under the requester_id specified
   *  @param int $requester_id ID Of the requester
   *  @param string $key JWT Token to save
   *  @return bool
   */
  public static function save($requester_id, $key) {
    $conn = PDOFactory::getFactory()->getConnection();

    $upd_jwt = $conn->prepare("UPDATE requester SET token = :token WHERE requester_id = :requester_id");
    $upd_jwt->bindParam(":token", $key);
    $upd_jwt->bindParam(":requester_id", $requester_id);

    return $upd_jwt->execute();
  }

  /**
   *  Generates a signed JSON web token with the associated user Data
   *  @param int $requester_id ID Of the requester
   *  @param string $email the Email Address of the requester
   *  @return string
   */
  public static function generate($requester_id, $email) {
    //Generate the JWT
    $tokenId = base64_encode(mcrypt_create_iv(32,MCRYPT_RAND));
    $issuedAt = time();
    $notBefore = $issuedAt + 10;  //Add 10 seconds
    $expire = $notBefore + 600;   //Add 10 minutes
    $serverName = getenv('SERVER_NAME');

    $data = array(
            'iat'  => $issuedAt,         // Issued at: time when the token was generated
            'jti'  => $tokenId,          // Json Token Id: an unique identifier for the token
            'iss'  => $serverName,       // Issuer
            //'nbf'  => $notBefore,        // Not before
            'exp'  => $expire,           // Expire
            'data' => array(                  // Data related to the signer user
                'userId'   => $requester_id, // userid from the requesters table
                'email' => $email, // User name
            )
          );

    $secretKey = base64_decode(getenv('JWT_SECRET'));

    //Sign the key with the server-only secretKey
    $jwt = \Firebase\JWT\JWT::encode(
      $data,      //Data to be encoded in JWT
      $secretKey, //signing key
      'HS512'     //Algorithm used to sign the token
      );

    return $jwt;
  }

  /**
   *  Fetches the row from the database that corresponds to the email address
   *  @param string $email Email address of the requester
   *  @param string $name Full Name of the requester to insert if none exists
   *  @return array
   */
  public static function fetchRequester($email, $name) {

    $conn = PDOFactory::getFactory()->getConnection();

    $get_requester = $conn->prepare("SELECT * from requester where email_address=:email");
    $get_requester->bindParam(":email", $email);
    $get_requester->execute();
    $row = $get_requester->fetch(PDO::FETCH_ASSOC);

    //Insert requester if none exists
    if (!$row) {
      $row = self::saveRequester($email, $name);
    }

    //Update requester if IP Address is not up to date
    if ($row['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
      $upd_requester = $conn->prepare("UPDATE requester SET ip_address=:ip_address WHERE requester_id=:requester_id");
      $upd_requester->bindParam(":ip_address", $_SERVER['REMOTE_ADDR']);
      $upd_requester->bindParam(":requester_id", $row['requester_id']);
      if (!$upd_requester->execute()) {
        //Query failed!
        throw new \Exception($upd_requester->errorInfo());
      }

      //Get updated record
      $row = self::fetchRequesterByID($row['requester_id']);
    }

    return $row;
  }

  /**
   * Saves a new requester object to the database
   * @param string $email Email Address of the new requester
   * @param string $name Full name of the new requester
   * @return array
   */
  private static function saveRequester($email, $name) {
    $conn = PDOFactory::getFactory()->getConnection();

    $ins_requester = $conn->prepare("INSERT INTO requester (name, email_address, ip_address) VALUES (:name, :email, :ip_address)");
    $ins_requester->bindParam(":name", $name);
    $ins_requester->bindParam(":email", $email);
    $ins_requester->bindParam(":ip_address", $_SERVER['REMOTE_ADDR']);
    $ins_requester->execute();
    //Get requester ID of inserted row
    $requester_id = $conn->lastInsertId();

    //Return the inserted row
    return self::fetchRequesterByID($requester_id);
  }

  /**
   * Fetches a requester from the database by ID
   * @param int $id Requester ID
   * @return array
   */
  private static function fetchRequesterByID($id) {
    //Initialize Database connection
    $conn = PDOFactory::getFactory()->getConnection();

    $sel_requester = $conn->prepare("SELECT * FROM requester WHERE requester_id=:requester_id");
    $sel_requester->bindParam(":requester_id", $id);
    $sel_requester->execute();
    return $sel_requester->fetch(PDO::FETCH_ASSOC);
  }

  /**
   * Checks the database to match a JWT
   * @param string $jwt JSON Web Token to search for
   * @return array
   */
  public static function authenticate($jwt) {
    $conn = PDOFactory::getFactory()->getConnection();

    $select = $conn->prepare("SELECT * FROM requester where token=:token");
    $select->bindParam(":token", $jwt);
    $select->execute();
    return $select->fetch(PDO::FETCH_ASSOC);;
  }
}


?>