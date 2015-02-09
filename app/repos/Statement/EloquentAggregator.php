<?php namespace Repos\Statement;

use \Models\Authority as Authority;
use \Locker\XApi\Helpers as XAPIHelpers;

interface AggregatorInterface {
  public function aggregate(Authority $authority, array $options);
}

class EloquentAggregator implements AggregatorInterface {
  const LL_STORED_TIME = 'timestamp';
  const XAPI_STORED_TIME = 'statement.timestamp';

  public function aggregate(Authority $authority, array $options) {
    $options = $this->getOptions($options);

    $pipeline = array_merge([[
      '$match' => array_merge($this->matchRange($options), $this->matchFilter($options['filter']))
    ]], $this->projectType($options['type'], $options));

    return (new EloquentGetter)->aggregate($authority, $pipeline);
  }

  private function getOptions(array $options) {
    $options = array_merge([
      'filter' => [],
      'type' => 'time',
      'interval' => 'day',
      'interval_length' => 1,
      'since' => null,
      'until' => null
    ], $options);
    $this->validateOptions($options);
    return $options;
  }

  private function validateOptions(array $options) {
    XAPIHelpers::checkType('filter', 'array', $options['filter']);
    XAPIHelpers::checkType('type', 'string', $options['type']);
    XAPIHelpers::checkType('interval', 'string', $options['interval']);
    XAPIHelpers::checkType('interval_length', 'integer', $options['interval_length']);
    if ($options['since'] !== null) XAPIHelpers::checkType('since', 'string', $options['since']);
    if ($options['until'] !== null) XAPIHelpers::checkType('until', 'string', $options['until']);
  }

  private function matchRange(array $options) {
    $since = $options['since'];
    $until = $options['until'];
    $match = [];

    if ($since !== null || $until !== null) $match[self::XAPI_STORED_TIME] = [];
    if ($since) $match[self::XAPI_STORED_TIME]['$gt'] = $since;
    if ($until) $match[self::XAPI_STORED_TIME]['$lt'] = $until;

    return $match;
  }

  private function matchFilter(array $filter) {
    // Converts values.
    $filter = array_map(function ($value) {
      if (is_array($filter)) {
        if ($filter[0] === '<>') {
          return ['$gt' => $value[1], '$lt' => $value[2]];
        } else {
          return ['$in' => $value];
        }
      } else {
        return $value;
      }
    }, $filter);

    // Converts keys.
    $match = [];
    foreach ($filter as $key => $value) {
      $match['$statement.'.$key] = $value;
    }

    return $match;
  }

  private function projectType($type, array $options) {
    switch ($type) {
      case 'time': return $this->aggregateTime($options['interval'], $options['interval_length']);
      case 'user': return $this->aggregateObject('actor');
      case 'verb': return $this->aggregateObject('verb');
      case 'activity': return $this->aggregateObject('object', 'activity');
      default: throw new \Exception('`type` must be a valid aggregation type (time, user, verb, activity) not `'.$type.'`.');
    }
  }

  private function aggregateObject($key, $alias = null) {
    $alias = $alias ?: $key;
    return [
      ['$group' => [
        '_id' => [$alias => '$statement.'.$key],
        'count' => ['$sum' => 1],
        'dates' => ['$addToSet' => '$'.self::XAPI_STORED_TIME],
        'data' => ['$addToSet' => '$statement.'.$key]
      ]],
      ['$unwind' => '$data'],
      ['$sort'  => ['count' => -1]],
      ['$project' => [
        '_id' => 0,
        'count' => 1,
        'dates' => 1,
        'data' => 1
      ]]
    ];
  }

  private function aggregateTime($interval = null, $interval_length = null) {
    return [
      ['$group' => $this->groupTimestamps($interval, $interval_length)],
      ['$sort'  => [
        'date' => 1
      ]],
      ['$project' => [
        '_id' => 0,
        'count'  => 1,
        'date' => 1
      ]]
    ];
  }

  private function groupTimestamps($interval = null, $interval_length = null, $group = []) {
    // Uses defaults.
    $interval = $interval ?: self::INTERVAL_DEFAULT;
    $interval_length = $interval_length ?: self::INTERVAL_LENGTH_DEFAULT;

    // Constructs the $interval to be grouped.
    switch ($interval) {
      case 'millisecond': $id['milliseconds'] = ['$millisecond' => '$'.self::LL_STORED_TIME];
      case 'second': $id['seconds'] = ['$second' => '$'.self::LL_STORED_TIME];
      case 'minute': $id['minutes'] = ['$minute' => '$'.self::LL_STORED_TIME];
      case 'hour': $id['hour'] = ['$hour' => '$'.self::LL_STORED_TIME];
      case 'day': $id['day'] = ['$dayOfMonth' => '$'.self::LL_STORED_TIME];
      case 'month': $id['month'] = ['$month' => '$'.self::LL_STORED_TIME];
      case 'year': $id['year'] = ['$year' => '$'.self::LL_STORED_TIME];
    }

    // Defines the length of the interval to be grouped.
    $mongo_interval = array_keys($id[$interval])[0];
    $id[$interval] = [
      '$subtract' => [
        [$mongo_interval => '$'.self::LL_STORED_TIME],
        ['$mod' => [[$mongo_interval => '$'.self::LL_STORED_TIME], $interval_length]]
      ]
    ];

    // Merges the group interval with other things to be grouped.
    return [
      '_id'   => $id,
      'count' => ['$sum' => 1],
      'date'  => ['$addToSet' => '$'. self::XAPI_STORED_TIME]
    ];
  }
}
