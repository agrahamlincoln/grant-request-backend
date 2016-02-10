<?php

namespace FormAPI;

class LogReader {
  public $selectedFile;
  public $logLocation;

  public function __construct() {
    $this->logLocation = getenv('LOG_LOCATION');

    $files = scandir($this->logLocation);

    $todays_file = array_filter($files, function($haystack) {
      $today = date("Y-m-d", time());
      return(strpos($haystack, $today));
    });

    $this->selectedFile = array_shift($todays_file);

  }

  public function getLastRequest() {
    $lines = array();
    $currentLine = '';
    $saveLines = false;

    $pos = -2; //Skip final new line character

    $file = $this->logLocation . '/' . $this->selectedFile;
    $fp = fopen($file, 'r');

    while (-1 !== fseek($fp, $pos, SEEK_END)) {
      //read next character from the line
      $char = fgetc($fp);
      if (PHP_EOL == $char) {
        //Line is complete, close it and add to array
        if ($line = json_decode($currentLine)) {
          //Line is valid JSON
          if (strpos($line->message, "==END") !== FALSE) {
            //Start operation block
            $saveLines = true;
          } elseif (strpos($line->message, "==START") !== FALSE) {
            //Reached the end of the block
            $saveLines = false;
          }

          if ($saveLines) {
            $lines[] = $currentLine;
          }
        }
        $currentLine = ''; //Reset the line before proceeding to the next
      } else {
        //Append the character to $currentLine
        $currentLine = $char . $currentLine;
      }
      $pos--;
    }
    //The while statement left the last line in variable, add this to the array
    if ($saveLines) {
      $lines[] = $currentLine;
    }


    return array_reverse($lines);
    //Read backwards from the file, start saving at the END statement and end saving at the BEGIN statement
    //reverse the order of the array to get the full log
  }

  public function getWarnings() {
    $lines = array();

    $lastRequest = $this->getLastRequest();

    foreach ($lastRequest as $line) {
      if ($decoded = json_decode($line)) {
        if ($decoded->logLevel == "WARNING") {
          $lines[] = $line;
        }
      }
    }

    return $lines;
  }

}