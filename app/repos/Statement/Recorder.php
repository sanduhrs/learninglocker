<?php namespace Repos\Statement;

use \Statement as ModelStatement;
use \Locker\XApi\Statement as XAPIStatement;

class Storer {
  private function store(array $statement, Authority $authority) {
    $ids = [];
    $models = array_map(function (XAPIStatement $statement) use ($authority) {
      $statement_id = $statement->getProp('id');

      // Removes statements that already exist.
      if (
        $this->checkConflict($statement, $authority) ||
        in_array($statement_id, $ids)
      ) return null;

      // Stores ID.
      $ids[] = $statement_id;

      $this->saveActivityProfile($statement, $authority);
      return $this->constructModel($statement, $authority);
    }, $statements);

    // Inserts statements.
    StatementRetriever::where($authority)->insert($models);

    // Validates voids.
    array_map(function (XAPIStatement $statement) use ($authority) {
      $this->validateVoid($statement, $authority);
    });
  }

  private function checkConflict(XAPIStatement $statement, Authority $authority) {
    $old_statement = StatementRetriever::where($authority)->where('statement.id', $statement->getProp('id'))->first();
    if ($old_statement !== null) {
      $old_statement = XAPIStatement::createFromJson($old_statement->toJson());
      $this->checkMatch($statement, $old_statement);
      return true;
    }
    return false;
  }

  private function checkMatch(XAPIStatement $new_statement, XAPIStatement $old_statement) {
    $new_statement = json_decode($new_statement_obj->toJson(), true);
    $old_statement = json_decode($old_statement_obj->toJson(), true);
    ksort(array_multisort($new_statement));
    ksort(array_multisort($old_statement));
    unset($new_statement['stored']);
    unset($old_statement['stored']);
    unset($new_statement['authority']);
    unset($old_statement['authority']);
    if ($new_statement !== $old_statement) {
      $new_statement = $new_statement_obj->toJson();
      $old_statement = $old_statement_obj->toJson();
      throw new Conflict(
        "Conflicts\r\n`$new_statement`\r\n`$old_statement`."
      );
    };
  }

  private function validateVoid(XAPIStatement $statement, Authority $authority) {
    $void_id = $statement->getPropValue('object.id');

    $voids = StatementRetriever::where($authority)->where('statement.id', $void_id)
      ->where('statement.object.id', '<>', 'http://adlnet.gov/expapi/verbs/voided')
      ->count();

    if ($voids !== 1) {
      throw new \Exception('Voiding invalid or nonexistant statement.');
    }
  }

  private function saveActivityProfile(XAPIStatement $statement, Authority $authority) {
    if ($statement->getPropValue('object.definition') !== null) {
      return ActivityProfile::store(
        $statement->getPropValue('object.id'),
        $statement->getPropValue('object.definition'),
        $authority
      );
    }
  }

  private function constructModel(XAPIStatement $statement, Authority $authority) {
    return [
      'statement' => $statement->getValue(),
      'active' => false,
      'voided' => false,
      'timestamp' => new \MongoDate(strtotime($statement->getPropValue('timestamp')))
    ];
  }
}

class Recorder {
  private $ids = [];

  public function store(array $statements, Authority $authority, array $attachments) {
    $statements = $this->constructStatements($statements, $authority);

    StatementStorer::store($statements, $authority);
    $this->updateReferences($statements, $authority);
    $this->voidStatements($statements, $authority);
    $this->activateStatements($statements, $authority);

    $this->storeAttachments($attachments, $authority);

    return $this->ids;
  }

  private function constructStatements(array $statements, Authority $authority) {
    return array_map(function (\stdClass $statement) {
      $statement->authority = $authority->actor;
      $statement->stored = Helpers::getCurrentDate();
      if (!$statement->timestamp) {
        $statement->timestamp = $statement->stored;
      }
      if (!$statement->id) {
        $statement->id = Helpers::makeUUID();
        $this->ids[] = $statement->id;
      }
      return new XAPIStatement($statement);
    }, $statements);
  }


}

class Recorder {
  public function store(array $statements, \Lrs $lrs, $attachments = '') {
    $statements = array_map(function (\stdClass $statement) {
      return new \Locker\XApi\Statement($statement);
    }, $statements);

    $statements = $this->validateStatements($statements, $lrs);
    $statements = $this->createStatements($statements, $lrs);
    $statements = $this->updateReferences($statements, $lrs);
    $statements = $this->voidStatements($statements, $lrs);
    $this->activateStatements($statements, $lrs);

    // Stores the $attachments.
    if ($attachments != '') {
      $this->storeAttachments($attachments, $lrs->_id);
    }
    return array_keys($statements);
  }

  private function validateStatements(array $statements, \Lrs $lrs) {
    $statements = $this->removeDuplicateStatements($statements, $lrs);
    $authority = $this->constructAuthority();
    $void_statements = [];

    foreach ($statements as $index => $statement) {
      $statement->setProp('authority', $authority);
      $errors = array_map(function ($error) {
        return (string) $error->addTrace('statement');
      }, $statement->validate());

      if (!empty($errors)) {
        throw new ValidationException($errors);
      } else {
        if ($this->isVoiding($statement->getValue())) {
          $void_statements[] = $statement->getPropValue('object.id');
        }
      }
    }
    if ($void_statements) {
      $this->validateVoid($statements, $lrs, $void_statements);
    }
    return $statements;
  }

  /**
   * Check that all void reference ids exist in the database and are not themselves void statements
   * @param array $statements
   * @param Lrs $lrs
   * @param array $references
   * @throws \Exception
   */
  private function validateVoid(array $statements, \Lrs $lrs, array $references) {
    $count = count($references);
    $reference_count = $this->statement
      ->where('lrs._id', $lrs->_id)
      ->whereIn('statement.id', $references)
      ->where('statement.verb.id', '<>', "http://adlnet.gov/expapi/verbs/voided")
      ->count();
    if ($reference_count != $count) {
      throw new \Exception('Voiding invalid or nonexistant statement');
    }
  }

  /**
   * Remove duplicate statements and generate ids
   *
   * @param array $statements
   * @param \Lrs $lrs
   * @return array
   */
  private function removeDuplicateStatements(array $statements, \Lrs $lrs) {
    $new_id_count = 0;
    $new_statements = [];
    $indexed_statements = [];
    foreach($statements as $index => $statement) {
      $statement_id = $statement->getPropValue('id');
      if ($statement_id !== null) {
        if (isset($this->sent_ids[$statement_id])) {
          $sent_statement = json_encode($this->sent_ids[$statement_id]);
          $current_statement = json_encode($statement);
          $this->checkMatch($new_statement, $current_statement);
          unset($statements[$index]);
        } else {
          $this->sent_ids[$statement_id] = $statement;
          $indexed_statements[$statement_id] = $statement;
        }
      } else {
        $new_statements[] = $statement;
      }
    }

    if (count($new_statements)) {
      $new_statements = $this->assignIds($new_statements, $lrs);
      $indexed_statements = array_merge($indexed_statements, $new_statements);
    }

    return $indexed_statements;
  }

  /**
   * @param array $statements
   * @param \Lrs $lrs
   * @return array List of statements with assigned id
   */
  private function assignIds(array $statements, \Lrs $lrs) {
    $indexed_statements = [];
    $count = count($statements);
    $uuids = $this->generateIds($count + 1);
    $duplicates = $this->checkIdsExist($uuids, $lrs);
    if ($duplicates) {
      $uuids = array_diff($uuids, $duplicates);
    }
    while(count($uuids) < $count) {
      $new_uuids = $this->generateIds($count - count($uuids));
      $duplicates = $this->checkIdsExist($new_uuids, $lrs);
      if ($duplicates) {
        $new_uuids = array_diff($uuids, $duplicates);
        $uuids = array_merge($new_uuids);
      }
    }

    foreach($statements as $statement) {
      $uuid = array_pop($uuids);
      $statement->setProp('id', $uuid);
      $indexed_statements[$uuid] = $statement;
    }
    return $indexed_statements;
  }

  private function checkMatch($new_statement, $old_statement) {
    $new_statement_obj = \Locker\XApi\Statement::createFromJson($new_statement);
    $old_statement_obj = \Locker\XApi\Statement::createFromJson($old_statement);
    $new_statement = json_decode($new_statement_obj->toJson(), true);
    $old_statement = json_decode($old_statement_obj->toJson(), true);
    array_multisort($new_statement);
    array_multisort($old_statement);
    ksort($new_statement);
    ksort($old_statement);
    unset($new_statement['stored']);
    unset($old_statement['stored']);
    if ($new_statement !== $old_statement) {
      $new_statement = $new_statement_obj->toJson();
      $old_statement = $old_statement_obj->toJson();
      throw new Conflict(
        "Conflicts\r\n`$new_statement`\r\n`$old_statement`."
      );
    };
  }

  /**
   * Check lrs for list of statement ids, optional list of statements by id for comparison
   *
   * @param array $uuids
   * @param \Lrs $lrs
   * @param array $statements
   * @return array List of duplicate ids
   */
  private function checkIdsExist(array $uuids, \Lrs $lrs, array $statements=null) {
    $duplicates = array();

    if ($uuids) {
      $existingModels = $this->statement
        ->where('lrs._id', $lrs->_id)
        ->whereIn('statement.id', $uuids)
        ->get();

      if(!$existingModels->isEmpty()) {
        foreach($existingModels as $existingModel) {
          $existingStatement = $existingModel->statement;
          $id = $existingStatement['id'];
          $duplicates[] = $id;
          if ($statements && isset($statements[$id])) {
            $statement = $statements[$id];
            $this->checkMatch($statement->toJson(), json_encode($existingStatement));
          }
        }
      }
    }
    return $duplicates;
  }

  /**
   * Generate an array of uuids of size $count
   *
   * @param integer $count
   * @return array List of uuids
   */
  private function generateIds($count) {
    $uuids = array();
    $validator = new \app\locker\statements\xAPIValidation();
    $i = 1;
    while ($i <= $count) {
      $uuid = $validator->makeUUID();
      if (isset($this->sent_ids[$uuid])) {
        continue;
      }
      $i++;
      $uuids[] = $uuid;
    }

    return $uuids;
  }

  /**
   * Create statements.
   * @param [Statement] $statements
   * @param Lrs $lrs
   * @return array list of statements
   */
  private function createStatements(array $statements, \Lrs $lrs) {
    if (count($this->sent_ids)) {
      // check for duplicates from statements with pre-assigned ids
      $this->checkIdsExist(array_keys($this->sent_ids), $lrs, $statements);
    }

    // Replaces '.' in keys with '&46;'.
    $statements = array_map(function (\Locker\XApi\Statement $statement) use ($lrs) {
      $replaceFullStop = function ($object, $replaceFullStop) {
        if ($object instanceof \Locker\XApi\Element) {
          $prop_keys = array_keys(get_object_vars($object->getValue()));
          foreach ($prop_keys as $prop_key) {
            $new_prop_key = str_replace('.', '&46;', $prop_key);
            $prop_value = $object->getProp($prop_key);
            $new_value = $replaceFullStop($prop_value, $replaceFullStop);
            $object->unsetProp($prop_key);
            $object->setProp($new_prop_key, $new_value);
          }
          return $object;
        } else {
          return $object;
        }
      };
      $replaceFullStop($statement, $replaceFullStop);

      return $this->makeStatement($statement, $lrs);
    }, $statements);

    $this->statement->where('lrs._id', $lrs->id)->insert(array_values($statements));
    return $statements;
  }

  /**
   * Sets references
   * @param array $statements
   * @param \Lrs $lrs
   * @return array list of statements with references
   */
  public function updateReferences(array $statements, \Lrs $lrs) {
    foreach($statements as $id => $statement) {
      if ($this->isReferencing($statement['statement'])) {
        // Finds the statement that it references.
        $refs = [];
        $this->recursiveCheckReferences($statements, $lrs, $refs, $statement['statement']->object->id);
        // Updates the refs.
        if ($refs) {
          $refs = array_values($refs);
          $statements[$id]['refs'] = $refs;
          $this->statement
            ->where('lrs._id', $lrs->id)
            ->where('statement.id', $id)->update([
              'refs' => $refs
            ]);
        }
      }
    }
    $this->updateReferrers($statements, $lrs);
    return $statements;
  }

  private function recursiveCheckReferences(array $statements, \Lrs $lrs, array &$refs, $id) {
    // check if $id refers to a statement being inserted
    if (isset($refs[$id])) {
      return $refs;
    }

    if (isset($statements[$id])) {
      $s = $statements[$id];
      $refs[$id] = $s->statement;
      if ($this->isReferencing($s->statement)) {
        $s_id = $s->statement->getPropValue('object.id');
        $this->recursiveCheckReferences($statements, $lrs, $refs, $s_id);
      }
    } else {
      $reference = $this->query->where($lrs->_id, [
        ['statement.id', '=', $id]
      ])->first();
      if ($reference) {
        $refs[$id] = $reference->statement;
        if ($this->isReferencing((object) $reference->statement)) {
          $s_id = $reference->statement['object']['id'];
          $this->recursiveCheckReferences($statements, $lrs, $refs, $s_id);
        }
      }
    }
    return $refs;
  }

  /**
   * Adds statement to refs in a existing referrer.
   * @param [Statement] $statements
   * @return [Statement]
   */
  private function updateReferrers(array $statements, \Lrs $lrs) {
    if (count($this->sent_ids)) {
      $referrers = $this->query->where($lrs->_id, [
          ['statement.object.id', 'in', array_keys($statements)],
          ['statement.object.objectType', '=', 'StatementRef'],
      ])->get();

      // Updates the refs $referrers.
      foreach ($referrers as $referrer) {
        $statement_id = $referrer['statement']['object']['id'];
        $statement = $statements[$statement_id];
        if (isset($statement['refs'])) {
          $referrer->refs = array(array_merge($statement['statement'], $statement['refs']));
        } else {
          $referrer->refs = array($statement['statement']);
        }
        if (!$referrer->save()) throw new \Exception('Failed to save referrer.');
      }
    }
    return $statements;
  }

  private function isReferencing(\stdClass $statement) {
    return (
      isset($statement->object->id) &&
      isset($statement->object->objectType) &&
      $statement->object->objectType === 'StatementRef'
    );
  }

  /**
   * Determines if a $statement voids another.
   * @param Statement $statement
   * @return boolean
   */
  private function isVoiding(\stdClass $statement) {
    if (($statement->verb->id === 'http://adlnet.gov/expapi/verbs/voided') && $this->isReferencing($statement)) {
      return true;
    }
    return false;
  }

  private function voidStatement($statement, $lrs) {
    if (!$this->isVoiding($statement['statement'])) return $statement;
    $reference = $this->query->where($lrs->_id, [
        ['statement.id', '=', $statement['statement']->object->id]
    ])->first();
    $ref_statement = json_decode(json_encode($reference->statement));
    if ($this->isVoiding($ref_statement)) {
       throw new \Exception('Cannot void a voiding statement');
    }
    $reference->voided = true;
    if (!$reference->save()) throw new \Exception('Failed to void statement.');
    return $statement;
  }

  public function voidStatements(array $statements, \Lrs $lrs) {
    return array_map(function (array $statement) use ($lrs) {
      return $this->voidStatement($statement, $lrs);
    }, $statements);
  }

  public function activateStatements(array $statements, \Lrs $lrs) {
    $updated = $this->statement->where('lrs._id', $lrs->id)->whereIn('statement.id', array_keys($statements))->update(array('active' => true));
  }

  /**
   * Constructs the authority.
   * https://github.com/adlnet/xAPI-Spec/blob/master/xAPI.md#authority
   * @return Authority
   */
  private function constructAuthority() {
    $client = (new \Client)
      ->where('api.basic_key', \LockerRequest::getUser())
      ->where('api.basic_secret', \LockerRequest::getPassword())
      ->first();

    if ($client != null && isset($client['authority'])) {
      return $client['authority'];
    } else {
      $site = \Site::first();
      return (object) [
        'name' => $site->name,
        'mbox' => 'mailto:' . $site->email,
        'objectType' => 'Agent'
      ];
    }
  }

  /**
   * Makes a $statement for the current $lrs.
   * @param Statement $statement
   * @param LRS $lrs
   * @return Statement
   */
  private function makeStatement(\Locker\XApi\Statement $statement, \Lrs $lrs) {
    // Uses defaults where possible.
    $currentDate = $this->getCurrentDate();
    $statement->setProp('stored', $currentDate);
    if ($statement->getPropValue('timestamp') === null) {
      $statement->setProp('timestamp', $currentDate);
    }

    // For now we store the latest submitted definition.
    // @todo this will change when we have a way to determine authority to edit.
    if ($statement->getPropValue('object.definition') !== null) {
      $this->activity->saveActivity(
        $statement->getPropValue('object.id'),
        $statement->getPropValue('object.definition')
      );
    }
    // Create a new statement model
    return [
      'lrs' => [
        '_id' => $lrs->_id,
        'name' => $lrs->title
      ],
      'statement' => $statement->getValue(),
      'active' => false,
      'voided' => false,
      'timestamp' => new \MongoDate(strtotime($statement->getPropValue('timestamp')))
    ];
  }

  /**
   * Check to see if a submitted statementId already exist and if so
   * are the two statements idntical? If not, return true.
   *
   * @param uuid   $id
   * @param string $lrs
   * @return boolean
   *
   **/
  private function doesStatementIdExist($lrsId, array $statement) {
    $existingModel = $this->statement
      ->where('lrs._id', $lrsId)
      ->where('statement.id', $statement['id'])
      ->first();

    if ($existingModel) {
      $existingStatement = json_encode($existingModel->statement);
      $this->checkMatch($existingStatement, json_encode($statement));
      return $existingModel;
    }

    return null;
  }

  /**
   * Store any attachments
   *
   **/
  private function storeAttachments( $attachments, $lrs ){

    foreach( $attachments as $attachment ){
      // Determines the delimiter.
      $delim = "\n";
      if (strpos($attachment, "\r".$delim) !== false) $delim = "\r".$delim;

      // Separate body contents from headers
      $attachment = ltrim($attachment, $delim);
      list($raw_headers, $body) = explode($delim.$delim, $attachment, 2);

      // Parse headers and separate so we can access
      $raw_headers = explode($delim, $raw_headers);
      $headers     = array();
      foreach ($raw_headers as $header) {
        list($name, $value) = explode(':', $header);
        $headers[strtolower($name)] = ltrim($value, ' ');
      }

      //get the correct ext if valid
      $ext = array_search( $headers['content-type'], FileTypes::getMap() );
      if( $ext === false ){
        \App::abort(400, 'This file type cannot be supported');
      }

      $filename = str_random(12) . "." . $ext;

      //create directory if it doesn't exist
      if (!\File::exists(base_path().'/uploads/'.$lrs.'/attachments/' . $headers['x-experience-api-hash'] . '/')) {
        \File::makeDirectory(base_path().'/uploads/'.$lrs.'/attachments/' . $headers['x-experience-api-hash'] . '/', 0775, true);
      }

      $destinationPath = base_path().'/uploads/'.$lrs.'/attachments/' . $headers['x-experience-api-hash'] . '/';

      $filename = $destinationPath.$filename;
      $file = fopen( $filename, 'wb'); //opens the file for writing with a BINARY (b) fla
      $size = fwrite( $file, $body ); //write the data to the file
      fclose( $file );

      if( $size === false ){
        \App::abort( 400, 'There was an issue saving the attachment');
      }
    }

  }

  /**
   * Gets the identifier key of an $agent.
   * @param Agent $actor
   * @return string
   */
  private function getAgentIdentifier($actor) {
    if (isset($actor['mbox'])) return 'mbox';
    if (isset($actor['account'])) return 'account';
    if (isset($actor['openid'])) return 'openid';
    if (isset($actor['mbox_sha1sum'])) return 'mbox_sha1sum';
    return null;
  }

  /**
   * Ids some parts of the $statement as defined by the spec.
   * https://github.com/adlnet/xAPI-Spec/blob/master/xAPI.md#723-getstatements
   * @param Statement $statement
   * @return Statement
   */
  private function getStatementIds(array $statement) {
    $actor = $statement['actor'];

    // Processes an anonymous group or actor.
    if (isset($actor['objectType']) && $actor['objectType'] === 'Group' && $this->getAgentIdentifier($actor) === null) {
      $members = [];
      foreach ($actor['members'] as $member) {
        $identifier = $this->getAgentIdentifier($member);
        $members[] = [
          $identifier => $member[$identifier]
        ];
      }
      $actor['members'] = $members;
    } else {
      $identifier = $this->getAgentIdentifier($actor);
      $actor = [
        $identifier => $actor[$identifier],
        'objectType' => isset($actor['objectType']) ? $actor['objectType'] : 'Agent'
      ];
    }

    // Replace parts of the statements.
    $statement['actor'] = $actor;
    $identifier = $this->getAgentIdentifier($statement['object']) ?: 'id';
    $statement['object'] = [
      $identifier => $statement['object'][$identifier],
      'objectType' => isset($statement['object']['objectType']) ? $statement['object']['objectType'] : 'Activity'
    ];

    return $statement;
  }
}
