<?php namespace Repos\Statement;

use \Models\Authority as Authority;
use \Helpers\Helpers as Helpers;

interface FormatterInterface {
  public function toCanonical(array $statements, array $langs);
  public function toIds(array $statements);
}

class EloquentFormatter implements FormatterInterface {
  public function toCanonical(array $statements, array $langs) {
    $statements = json_decode(json_encode($statements));
    return array_map(function (\stdClass $statement) use ($langs) {
      return $this->getStatementCanonical($statement, $langs);
    }, $statements);
  }

  public function toIds(array $statements) {
    $statements = json_decode(json_encode($statements));
    return array_map(function (\stdClass $statement) {
      return $this->getStatementIds($statement);
    }, $statements);
  }

  private function getStatementCanonical(\stdClass $statement, array $langs) {
    if (isset($statement->object->definition)) {
      $definition = $statement->object->definition;

      if (isset($definition->name)) {
        $definition->name = $this->canonicalise($definition->name, $langs);
      }
      if (isset($definition->description)) {
        $definition->description = $this->canonicalise($definition->description, $langs);
      }

      $statement->object->definition = $definition;
    }

    if (isset($statement->verb->display)) {
      $statement->verb->display = $this->canonicalise($statement->verb->display, $langs);
    }
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

  private function getStatementIds(\stdClass $statement) {
    $actor = $statement->actor;

    // Processes an anonymous group or actor.
    if (
      $actor->objectType === 'Group' &&
      Helpers::getAgentIdentifier($actor) === null
    ) {
      $actor->members = array_map(function (\stdClass $member) {
        return $this->identifyObject($member, Helpers::getAgentIdentifier($member));
      }, $actor->members);
    } else {
      $actor = $this->identifyObject($actor, Helpers::getAgentIdentifier($actor));
    }

    // Replace parts of the statements.
    $statement->actor = $actor;
    $statement->object = $this->identifyObject(
      $statement->object,
      Helpers::getAgentIdentifier($statement->object) ?: 'id'
    );
    return $statement;
  }

  private function identifyObject($object, $identifier) {
    return (object) [
      $identifier => $object->{$identifier},
      'objectType' => $object->objectType
    ];
  }
}
