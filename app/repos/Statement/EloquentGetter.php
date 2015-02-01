<?php namespace Repos\Statement;

use \Models\Authority as Authority;
use \Models\Statement as Statement;
use \Helpers\Exceptions\NotFound as NotFoundException;
use \DB as Mongo;
use \Helpers\Helpers as Helpers;

interface GetterInterface {
  public function aggregate(Authority $authority, array $pipeline);
  public function index(Authority $authority, array $options);
  public function show(Authority $authority, $id, $voided = false, $active = true);
  public function where(Authority $authority);
}

class EloquentGetter implements GetterInterface {
  const DEFAULT_LIMIT = 100;

  public function aggregate(Authority $authority, array $pipeline) {
    // Stops people using the $out operator.
    if (strpos(json_encode($pipeline), '$out') !== false) return;

    // Ensures that you can't get statements from other LRSs.
    $pipeline[0] = array_merge_recursive([
      '$match' => ['lrs._id' => $authority->getLRS()]
    ], $pipeline[0]);

    return Mongo::getMongoDB()->statements->aggregate($pipeline);
  }

  public function index(Authority $authority, array $options) {
    // Gets and validates options.
    $options = $this->getIndexOptions($options);
    $this->validateIndexOptions($options);

    // Constructs pipelines and aggregates statements.
    $index_pipeline = $this->constructIndexPipeline($options);
    $statements_pipeline = $this->projectLimitedStatements($index_pipeline, $options);
    $count_pipeline = $this->projectCountedStatements($index_pipeline);
    $statements = $this->aggregate($authority, $statements_pipeline)['result'];
    $count = $this->aggregate($authority, $count_pipeline)['result'];
    $count = isset($count[0]['count']) ? $count[0]['count'] : 0;

    // Formats statements.
    switch ($options['format']) {
      case 'exact': $statements = $statements; break;
      case 'ids': $statements = (new EloquentFormatter)->toIds($statements); break;
      case 'canonical': $statements = (new EloquentFormatter)->toCanonical($statements, $options['langs']); break;
      default: throw new \Exception('Invalid format.');
    }

    return [Helpers::replaceHTMLDots($statements), $count];
  }

  public function show(Authority $authority, $id, $voided = false, $active = true) {
    $statement = $this
      ->where($authority)
      ->where('statement.id', $id)
      ->where('voided', $voided)
      ->where('active', $active)
      ->first();

    if ($statement === null) throw new NotFoundException($id, 'Statement');

    return $Helpers::replaceHTMLDots($statement);
  }

  public function where(Authority $authority) {
    return Statement::where('lrs._id', $authority->getLRS());
  }

  private function constructIndexPipeline(array $options) {
    $pipeline = [[
      '$match' => $this->constructMatchPipeline($options)
    ]];

    // Sorts statements.
    $order = $options['ascending'] === true ? 1 : -1;
    $pipeline[] = ['$sort' => ['statement.stored' => $order]];

    return $pipeline;
  }

  private function projectLimitedStatements(array $pipeline, array $options) {
    // Limit and offset.
    $pipeline[] = ['$skip' => (int) $options['offset']];
    $pipeline[] = ['$limit' => (int) $options['limit']];

    // Outputs statement properties.
    $pipeline[] = ['$group' => $this->groupStatementProps()];
    $pipeline[] = ['$project' => $this->projectStatementProps()];

    return $pipeline;
  }

  private function projectCountedStatements(array $pipeline) {
    $pipeline[] = ['$group' => [
      '_id' => '$lrs._id',
      'count' => ['$sum' => 1]
    ]];
    $pipeline[] = ['$project' => [
      '_id' => 0,
      'count' => 1
    ]];

    return $pipeline;
  }

  private function groupStatementProps() {
    return [
      '_id' => '$statement.id',
      'id' => ['$first' => '$statement.id'],
      'actor' => ['$first' => '$statement.actor'],
      'verb' => ['$first' => '$statement.verb'],
      'object' => ['$first' => '$statement.object'],
      'result' => ['$first' => '$statement.result'],
      'context' => ['$first' => '$statement.context'],
      'timestamp' => ['$first' => '$statement.timestamp'],
      'stored' => ['$first' => '$statement.stored'],
      'authority' => ['$first' => '$statement.authority'],
      'version' => ['$first' => '$statement.version']
    ];
  }

  private function projectStatementProps() {
    return [
      '_id' => 0,
      'id' => 1,
      'actor' => 1,
      'verb' => 1,
      'object' => 1,
      'result' => 1,
      'context' => 1,
      'timestamp' => 1,
      'stored' => 1,
      'authority' => 1,
      'version' => 1
    ];
  }

  private function constructMatchPipeline(array $options) {
    return $this->addMatchOptions([], $options, [
      'agent' => function ($agent, array $options) {
        Helpers::validateAtom(\Locker\XApi\Agent::createFromJSON($agent));
        return $this->matchAgent($agent, $options);
      },
      'verb' => function ($verb) {
        Helpers::validateAtom(new \Locker\XApi\IRI($verb));
        return ['statement.verb.id' => $verb];
      },
      'registration' => function ($registration) {
        Helpers::validateAtom(new \Locker\XApi\UUID($registration));
        return ['statement.context.registration' => $registration];
      },
      'activity' => function ($activity, array $options) {
        Helpers::validateAtom(new \Locker\XApi\IRI($activity));
        return $this->matchActivity($activity, $options);
      },
      'since' => function ($since) {
        Helpers::validateAtom(new \Locker\XApi\Timestamp($since));
        return ['statement.stored' => ['$gt' => $since]];
      },
      'until' => function ($until) {
        Helpers::validateAtom(new \Locker\XApi\Timestamp($until));
        return ['statement.stored' => ['$lt' => $until]];
      },
      'active' => function ($active) {
        Helpers::validateAtom(new \Locker\XApi\Boolean($active));
        return ['active' => $active];
      },
      'voided' => function ($voided) {
        Helpers::validateAtom(new \Locker\XApi\Boolean($voided));
        return ['voided' => $voided];
      }
    ]);
  }

  private function addMatchOptions(array $match, array $options, array $matchers) {
    $match = [];
    foreach ($matchers as $option => $matcher) {
      $match = $this->addMatch($match, $options, $option, $matcher);
    }
    return $match;
  }

  private function addMatch(array $match, array $options, $option, callable $matcher) {
    if (!isset($options[$option]) || $options[$option] === null) return $match;
    return array_merge_recursive($match, $matcher($options[$option], $options));
  }

  private function matchAgent($agent, array $options) {
    $agent = json_decode($agent);
    if (gettype($agent) !== 'object') throw new \Exception('Invalid agent');

    $identifier_key = Helpers::getAgentIdentifier($agent);
    $identifier_value = $agent->{$identifier_key};

    return $this->matchOption($identifier_value, $options['related_agents'], [
      "statement.actor.$identifier_key",
      "statement.object.$identifier_key"
    ], [
      "statement.authority.$identifier_key",
      "statement.context.instructor.$identifier_key",
      "statement.context.team.$identifier_key"
    ]);
  }

  private function matchActivity($activity, array $options) {
    return $this->matchOption($activity, $options['related_activities'], [
      'statement.object.id'
    ], [
      'statement.context.contextActivities.parent.id',
      'statement.context.contextActivities.grouping.id',
      'statement.context.contextActivities.category.id',
      'statement.context.contextActivities.other.id'
    ]);
  }

  private function matchOption($value, $option, array $less, array $more) {
    $or = [];

    if ((bool) $option === true) {
      foreach ($more as $key) {
        $or[] = [$key => $value];
      }
    }

    foreach ($less as $key) {
      $or[] = [$key => $value];
    }

    return [
      '$or' => $or
    ];
  }

  private function validateIndexOptions(array $options) {
    if ($options['offset'] < 0) throw new \Exception('`offset` must be a positive interger.');
    if ($options['limit'] < 0) throw new \Exception('`limit` must be a positive interger.');
    Helpers::validateAtom(new \Locker\XApi\Boolean($options['related_agents']));
    Helpers::validateAtom(new \Locker\XApi\Boolean($options['related_activities']));
    Helpers::validateAtom(new \Locker\XApi\Boolean($options['attachments']));
    Helpers::validateAtom(new \Locker\XApi\Boolean($options['active']));
    Helpers::validateAtom(new \Locker\XApi\Boolean($options['voided']));
    Helpers::validateAtom(new \Locker\XApi\Boolean($options['ascending']));
  }

  private function getIndexOptions(array $given_options) {
    return $this->getOptions($given_options, [
      'agent' => null,
      'activity' => null,
      'verb' => null,
      'registration' => null,
      'since' => null,
      'until' => null,
      'active' => true,
      'voided' => false,
      'related_activities' => false,
      'related_agents' => false,
      'ascending' => false,
      'format' => 'exact',
      'offset' => 0,
      'limit' => self::DEFAULT_LIMIT,
      'langs' => [],
      'attachments' => false
    ]);
  }

  private function getOptions(array $given_options, array $defaults) {
    $options = [];

    foreach ($defaults as $key => $default) {
      $options[$key] = $this->getOption($given_options, $key, $default);
    }

    return $options;
  }

  private function getOption(array $given_options, $key, $default) {
    return (
      isset($given_options[$key]) && $given_options[$key] !== null ?
      $given_options[$key] :
      $default
    );
  }
}
