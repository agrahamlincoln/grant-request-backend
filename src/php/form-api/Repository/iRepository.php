<?php

namespace FormAPI\Repository;

interface iRepository
{
  public function fetch($id);
  public function save($obj);
  public function delete($obj);
}

?>