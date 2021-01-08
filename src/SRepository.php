<?php
namespace SoftDelete;

use Adianti\Database\TRepository;

class SRepository extends TRepository {
  public function load(TCriteria $criteria = NULL, $callObjectLoad = TRUE)
  {
      if (!$criteria)
      {
          $criteria = isset($this->criteria) ? $this->criteria : new TCriteria;
      }
      $criteria->add(new TFilter('status', '!=', 'D'));
//        var_dump($criteria->dump());

      // creates a SELECT statement
      $sql = new TSqlSelect;
      $sql->addColumn($this->getAttributeList());
      $sql->setEntity($this->getEntity());
      // assign the criteria to the SELECT statement
      $sql->setCriteria($criteria);

      // get the connection of the active transaction
      if ($conn = TTransaction::get())
      {
          // register the operation in the LOG file
          TTransaction::log($sql->getInstruction());
          $dbinfo = TTransaction::getDatabaseInfo(); // get dbinfo
          if (isset($dbinfo['prep']) AND $dbinfo['prep'] == '1') // prepared ON
          {
              $result = $conn-> prepare ( $sql->getInstruction( TRUE ) , array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
              $result-> execute ( $criteria->getPreparedVars() );
          }
          else
          {
              // execute the query
              $result= $conn-> query($sql->getInstruction());
          }
          $results = array();

          $class = $this->class;
          $callback = array($class, 'load'); // bypass compiler

          // Discover if load() is overloaded
          $rm = new ReflectionMethod($class, $callback[1]);

          if ($result)
          {
              // iterate the results as objects
              while ($raw = $result-> fetchObject())
              {
                  $object = new $this->class;
                  if (method_exists($object, 'onAfterLoadCollection'))
                  {
                      $object->onAfterLoadCollection($raw);
                  }
                  $object->fromArray( (array) $raw);

                  if ($callObjectLoad)
                  {
                      // reload the object because its load() method may be overloaded
                      if ($rm->getDeclaringClass()-> getName () !== 'Adianti\Database\TRecord')
                      {
                          $object->reload();
                      }
                  }

                  if ( ($cache = $object->getCacheControl()) && empty($this->columns))
                  {
                      $pk = $object->getPrimaryKey();
                      $record_key = $class . '['. $object->$pk . ']';
                      if ($cache::setValue( $record_key, $object->toArray() ))
                      {
                          TTransaction::log($record_key . ' stored in cache');
                      }
                  }
                  // store the object in the $results array
                  $results[] = $object;
              }
          }
          return $results;
      }
      else
      {
          // if there's no active transaction opened
          throw new Exception(AdiantiCoreTranslator::translate('No active transactions') . ': ' . __METHOD__ .' '. $this->getEntity());
      }
  }
}
