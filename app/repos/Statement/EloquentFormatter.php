<?php namespace Repos\Statement;

use \Models\Authority as Authority;
use \Helpers\Helpers as Helpers;

interface FormatterInterface {
  public function toCanonical(array $statements, array $langs);
  public function toIds(array $statements);
}


// https://github.com/adlnet/xAPI-Spec/blob/master/xAPI.md#details-34
class EloquentFormatter implements FormatterInterface {
  /**
   * Converts the given statements to their canonical form using the given languages.
   * @param [\stdClass] $statements
   * @param [Stromg] $langs
   * @return [\stdClass]
   */
  public function toCanonical(array $statements, array $langs) {
    $statements = $statements;
    return array_map(function (\stdClass $statement) use ($langs) {
      return $this->getStatementCanonical($statement, $langs);
    }, $statements);
  }

  /**
   * Converts the given statements to their IDs form.
   * @param [\stdClass] $statements
   * @return [\stdClass]
   */
  public function toIds(array $statements) {
    $statements = $statements;
    return array_map(function (\stdClass $statement) {
      return $this->getStatementIds($statement);
    }, $statements);
  }

  /**
   * Converts the given statement to its canonical form using the given languages.
   * @param \stdClass $statement
   * @param [String] $langs
   * @return \stdClass
   */
  private function getStatementCanonical(\stdClass $statement, array $langs) {
    // Canocalises the object.
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

    // Canocalises the verb.
    if (isset($statement->verb->display)) {
      $statement->verb->display = $this->canonicalise($statement->verb->display, $langs);
    }
    return $statement;
  }

  /**
   * Converts the given display to its canonical form using the given languages.
   * @param \stdClass $display
   * @param [String] $langs
   * @return String
   */
  private function canonicalise(\stdClass $display, array $langs) {
    $display_langs = array_keys((array) $display);

    // Determines the acceptable languages.
    $acceptable_langs = array_filter($display_langs, function ($display_lang) use ($langs) {
      return in_array($display_lang, $langs);
    });
    $acceptable_langs = array_values($acceptable_langs);

    // Returns the canonicalised display.
    if (count($acceptable_langs) > 0) {
      return $display->{$acceptable_langs[0]};
    } else if (count($display_langs) > 0) {
      return $display->{$display_langs[0]};
    } else {
      return null;
    }
  }

  /**
   * Converts the given statement to its IDs form.
   * @param [\stdClass] $statement
   * @return [\stdClass]
   */
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

  /**
   * Converts the given object to its ID form.
   * @param \stdClass $object Object to be converted.
   * @param String $identifier The identifier to be used.
   * @return \stdClass
   */
  private function identifyObject(\stdClass $object, $identifier) {
    return (object) [
      $identifier => $object->{$identifier},
      'objectType' => $object->objectType
    ];
  }
}
