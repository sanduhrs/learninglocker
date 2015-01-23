<?php namespace Repos\Statement;

interface FormatterInterface {
  public function toCanonical(array $statements, array $langs);
  public function toIds(array $statements);
}

class EloquentFormatter implements FormatterInterface {
  public function toCanonical(array $statements, array $langs) {
    return array_map(function (XAPIStatement $statement) use ($langs) {
      return $this->getStatementCanonical($statement, $langs);
    }, $statements);
  }

  public function toIds(array $statements) {
    return array_map(function (XAPIStatement $statement) {
      return $this->getStatementIds($statement);
    }, $statements);
  }

  private function getStatementCanonical(XAPIStatement $statement, array $langs) {
    $statement_value = $statement->getValue();
    $definition = $statement->object->definition;

    if (isset($definition->name)) {
      $definition->name = $this->canonicalise($definition->name, $langs);
    }
    if (isset($definition->description)) {
      $definition->description = $this->canonicalise($definition->description, $langs);
    }

    $statement->object->definition = $definition;
    return $statement;
  }

  private function canonicalise(\stdClass $display, array $langs) {
    $display_langs = array_keys((array) $display);

    $acceptable_langs = array_filter($display_langs, function ($display_lang) use ($langs) {
      return in_array($display_lang, $langs);
    });
    
    $acceptable_langs = array_values($acceptable_langs);

    if (count($acceptable_langs) > 0) {
      return $display->{$acceptable_langs[0]};
    } else if (count($display_langs) > 0) {
      return $display->{$display_langs[0]};
    } else {
      return null;
    }
  }

  private function getStatementIds(XAPIStatement $statement) {
    $statement_value = $statement->getValue();
    $actor = $statement_value->actor;

    // Processes an anonymous group or actor.
    if (
      $actor->objectType === 'Group' &&
      $this->getAgentIdentifier($actor) === null
    ) {
      $actor->members = array_map(function ($member) {
        return $this->identifyObject($member, $this->getAgentIdentifier($member));
      }, $actor->members);
    } else {
      $actor = $this->identifyObject($actor, $this->getAgentIdentifier($actor));
    }

    // Replace parts of the statements.
    $statement_value->actor = $actor;
    $statement_value->object = $this->identifyObject(
      $statement_value->object,
      $this->getAgentIdentifier($statement_value->object) ?: 'id'
    );
    return $statement_value;
  }

  private function identifyObject($object, $identifier) {
    return (object) [
      $identifier => $object->{$identifier},
      'objectType' => $object->objectType
    ];
  }

  private function getAgentIdentifier($actor) {
    if (isset($actor->mbox)) return 'mbox';
    if (isset($actor->account)) return 'account';
    if (isset($actor->openid)) return 'openid';
    if (isset($actor->mbox_sha1sum)) return 'mbox_sha1sum';
    return null;
  }
}
