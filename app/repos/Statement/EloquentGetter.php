<?php namespace Repos\Statement;

use \Models\Authority as Authority;
use \Models\Statement as Statement;
use \Helpers\Exceptions\NotFound as NotFoundException;
use \Helpers\Helpers as Helpers;
use \DB as Mongo;

interface GetterInterface {
  public function aggregate(Authority $authority, array $pipeline);
  public function index(Authority $authority, array $options);
  public function show(Authority $authority, $id, $voided = false, $active = true);
  public function where(Authority $authority);
}

class EloquentGetter implements GetterInterface {
  const DEFAULT_LIMIT = 100;

  /**
   * Aggregates statements using the given pipeline and authority.
   * @param Authority $authority The authority to restrict with.
   * @param [[String => mixed]] $pipeline Mongo pipeline.
   * @return [\stdClass]
   */
  public function aggregate(Authority $authority, array $pipeline) {
    // Stops people using the $out operator.
    if (strpos(json_encode($pipeline), '$out') !== false) return;

    // Ensures that you can't get statements from other LRSs.
    $homePage = Helpers::replaceDots($authority->homePage);
    $pipeline[0] = array_merge_recursive([
      '$match' => [
        'statement.authority.account.homePage' => new MongoRegex("/^$homePage/")
      ]
    ], $pipeline[0]);

    return Mongo::getMongoDB()->statements->aggregate($pipeline);
  }

  /**
   * Gets all of the statements that fulfil the given options.
   * @param Authority $authority The authority to restrict with.
   * @param [String => mixed] $options Index options.
   * @return [[\stdClass], Integer] Contains the statements and the number of statements fulfilling the filtering options.
   */
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

  /**
   * Gets a single statement that has the given ID.
   * @param Authority $authority The authority to restrict with.
   * @param String $id ID of the statement to be found.
   * @param Boolean $voided Determines if the statement has been voided.
   * @param Boolean $active Determines if the statement has been activated.
   * @return \stdClass
   */
  public function show(Authority $authority, $id, $voided = false, $active = true) {
    $statement = $this
      ->where($authority)
      ->where('statement.id', $id)
      ->where('voided', $voided)
      ->where('active', $active)
      ->first();

    if ($statement === null) throw new NotFoundException($id, 'Statement');

    return Helpers::replaceHTMLDots($statement);
  }

  /**
   * Constructs a query restricted by the given authority.
   * @param Authority $authority The authority to restrict with.
   * @return \Jenssegers\Mongodb\Eloquent\Builder
   */
  public function where(Authority $authority) {
    return Statement::where(
      'statement.authority.account.homePage',
      'like',
      $authority->homePage.'%'
    );
  }

  /**
   * Extends a Mongo pipeline with a Mongo pipeline from the given options.
   * @param [String => mixed] $options Index options.
   * @return [[String => mixed]] $pipeline Mongo pipeline.
   */
  private function constructIndexPipeline(array $options) {
    $pipeline = [[
      '$match' => $this->constructMatchPipeline($options)
    ]];

    return $pipeline;
  }

  /**
   * Extends a Mongo pipeline with a Mongo projection from the given options to get a limited number of statements.
   * @param [[String => mixed]] $pipeline Mongo pipeline.
   * @param [String => mixed] $options Index options.
   * @return [[String => mixed]] $pipeline Mongo pipeline.
   */
  private function projectLimitedStatements(array $pipeline, array $options) {
    $pipeline[] = ['$group' => $this->groupStatementProps()];

    // Limit and offset.
    $pipeline[] = ['$skip' => $options['offset']];
    $pipeline[] = ['$limit' => $options['limit']];

    // Sorts statements.
    $order = $options['ascending'] === true ? 1 : -1;
    $pipeline[] = ['$sort' => ['stored' => $order]];

    // Outputs statement properties.
    $pipeline[] = ['$project' => $this->projectStatementProps()];

    return $pipeline;
  }

  /**
   * Constructs a Mongo projection to get a count of statements.
   * @param [[String => mixed]] $pipeline Mongo pipeline.
   * @return [[String => mixed]] $pipeline Mongo pipeline.
   */
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

  /**
   * Constructs a Mongo grouping to get the properties of statements.
   * @return [String => mixed]
   */
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

  /**
   * Constructs a Mongo projection to get the properties of statements.
   * @return [String => mixed]
   */
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

  /**
   * Constructs a Mongo pipeline from the given options.
   * @param [String => mixed] $options Index options.
   * @return [[String => mixed]] $pipeline Mongo pipeline.
   */
  private function constructMatchPipeline(array $options) {
    return $this->addMatchOptions([], $options, [
      'agent' => function ($agent, array $options) {
        Helpers::validateAtom(\Locker\XApi\Agent::createFromJSON($agent));
        return $this->matchAgent($agent, $options);
      },
      'verb' => function ($verb) {
        Helpers::validateAtom(new \Locker\XApi\IRI($verb));
        return ['statement.verb.id' => Helpers::replaceDots($verb)];
      },
      'registration' => function ($registration) {
        Helpers::validateAtom(new \Locker\XApi\UUID($registration));
        return ['statement.context.registration' => $registration];
      },
      'activity' => function ($activity, array $options) {
        Helpers::validateAtom(new \Locker\XApi\IRI($activity));
        return $this->matchActivity(Helpers::replaceDots($activity), $options);
      },
      'since' => function ($since) {
        Helpers::validateAtom(new \Locker\XApi\Timestamp($since));
        return ['statement.stored' => ['$gt' => Helpers::replaceDots($since)]];
      },
      'until' => function ($until) {
        Helpers::validateAtom(new \Locker\XApi\Timestamp($until));
        return ['statement.stored' => ['$lte' => Helpers::replaceDots($until)]];
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

  /**
   * Extends a given Mongo match using the given options and matchers.
   * @param [String => mixed] $match Mongo match to be extended.
   * @param [String => mixed] $options Index options.
   * @param [Callable] $matchers
   * @return [String => mixed]
   */
  private function addMatchOptions(array $match, array $options, array $matchers) {
    $match = $match ?: [];
    foreach ($matchers as $option => $matcher) {
      $match = $this->addMatch($match, $options, $option, $matcher);
    }
    return $match;
  }

  /**
   * Extends a given Mongo match using the given options, option, and matcher.
   * @param [String => mixed] $match Mongo match to be extended.
   * @param [String => mixed] $options Index options.
   * @param String $option Option to be given to the matcher.
   * @param Callable $matcher
   * @return [String => mixed]
   */
  private function addMatch(array $match, array $options, $option, callable $matcher) {
    if (!isset($options[$option]) || $options[$option] === null) return $match;
    return array_merge_recursive($match, $matcher($options[$option], $options));
  }

  /**
   * Constructs a Mongo match using the given agent and options.
   * @param String $agent Agent to be matched.
   * @param [String => mixed] $options Index options.
   * @return [String => mixed]
   */
  private function matchAgent($agent, array $options) {
    $agent = json_decode($agent);
    if (gettype($agent) !== 'object') throw new \Exception('Invalid agent');

    $identifier_key = Helpers::getAgentIdentifier($agent);
    $identifier_value = $agent->{$identifier_key};

    return $this->matchOption(Helpers::replaceDots($identifier_value), $options['related_agents'], [
      "statement.actor.$identifier_key",
      "statement.object.$identifier_key"
    ], [
      "statement.authority.$identifier_key",
      "statement.context.instructor.$identifier_key",
      "statement.context.team.$identifier_key"
    ]);
  }

  /**
   * Constructs a Mongo match using the given activity and options.
   * @param String $activity Activity to be matched.
   * @param [String => mixed] $options Index options.
   * @return [String => mixed]
   */
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

  /**
   * Constructs a Mongo match for the given value using the given option and fields (less and more) to be matched.
   * @param mixed $value
   * @param mixed $option
   * @return [String] $less Fields to be matched regardless of the given option.
   * @return [String] $less Fields to be matched additionally if the given option is `true`.
   */
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

  /**
   * Validates the given options as index options.
   * @param [String => mixed] $options Index options.
   */
  private function validateIndexOptions(array $options) {
    if ($options['offset'] < 0) throw new \Exception('`offset` must be a positive interger.');
    if ($options['limit'] < 1) throw new \Exception('`limit` must be a positive interger.');
    Helpers::validateAtom(new \Locker\XApi\Boolean($options['related_agents']), 'related_activities');
    Helpers::validateAtom(new \Locker\XApi\Boolean($options['related_activities']), 'related_activities');
    Helpers::validateAtom(new \Locker\XApi\Boolean($options['attachments']), 'attachments');
    Helpers::validateAtom(new \Locker\XApi\Boolean($options['ascending']), 'ascending');
  }

  /**
   * Returns all of the index options set to their default or given value (using the given options).
   * @param [String => mixed] $given_options Index options.
   * @return [String => mixed]
   */
  private function getIndexOptions(array $given_options) {
    // Merges with defaults.
    $options = $this->getOptions($given_options, [
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

    // Converts types.
    $options['active'] = $this->convertToBoolean($options['active']);
    $options['voided'] = $this->convertToBoolean($options['voided']);
    $options['related_agents'] = $this->convertToBoolean($options['related_agents']);
    $options['related_activities'] = $this->convertToBoolean($options['related_activities']);
    $options['attachments'] = $this->convertToBoolean($options['attachments']);
    $options['ascending'] = $this->convertToBoolean($options['ascending']);
    $options['limit'] = $this->convertToInt($options['limit']);
    $options['offset'] = $this->convertToInt($options['offset']);

    if ($options['limit'] === 0) $options['limit'] = self::DEFAULT_LIMIT;
    return $options;
  }

  /**
   * Converts the given value to a Boolean if it can be.
   * @param mixed $value
   * @return Boolean|mixed Returns the value unchanged if it can't be converted.
   */
  private function convertToBoolean($value) {
    if (gettype($value) === 'string') $value = strtolower($value);
    if ($value === 'true') return true;
    if ($value === 'false') return false;
    return $value;
  }

  /**
   * Converts the given value to a Integer if it can be.
   * @param mixed $value
   * @return Integer|mixed Returns the value unchanged if it can't be converted.
   */
  private function convertToInt($value) {
    $converted_value = (int) $value;
    return ($value !== (string) $converted_value) ? $value : $converted_value;
  }

  /**
   * Returns all of the options set to their default (using given defaults) or given value (using the given options).
   * @param [String => mixed] $given_options Index options.
   * @param [String => mixed] $defaults Index options defaults.
   * @return [String => mixed]
   */
  private function getOptions(array $given_options, array $defaults) {
    $options = [];

    foreach ($defaults as $key => $default) {
      $options[$key] = $this->getOption($given_options, $key, $default);
    }

    return $options;
  }

  /**
   * Returns the value associated with the key from the given options or the given default.
   * @param [String => mixed] $given_options Index options.
   * @param String $key Name of the option to be returned.
   * @param mixed $default Default value for the option.
   * @return mixed
   */
  private function getOption(array $given_options, $key, $default) {
    return (
      isset($given_options[$key]) && $given_options[$key] !== null ?
      $given_options[$key] :
      $default
    );
  }
}
