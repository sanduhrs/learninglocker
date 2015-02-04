<?php namespace Repos\Document\State;

use \Jenssegers\Mongodb\Eloquent\Builder as Builder;
use \Repos\Document\EloquentRepository as DocumentRepository;
use \Helpers\Helpers as Helpers;

class EloquentRepository extends DocumentRepository {
  protected static $document_type = 'state';
  protected static $document_identifier = 'stateId';

  protected static $ap_props = [
    'stateId',
    'activityId',
    'agent',
    'registration'
  ];
  protected static $data_props = [
    'activityId' => null,
    'agent' => null,
    'registration' => null,
    'stateId' => null,
    'since' => null,
    'content_info' => null,
    'method' => 'POST',
    'updated' => null
  ];

  /**
   * Extends a query to match multiple documents using the given data.
   * @param Builder $query Query to extend.
   * @param AssocArray $data Data from the request.
   * @return Builder
   */
  protected function constructIndexQuery(Builder $query, array $data) {
    if (!isset($data['activityId'])) throw new \Exception(
      'Missing activityId'
    );
    $query = $query->where('documentType', static::$document_type);
    $query = $query->where('activityId', $data['activityId']);
    $query = $this->whereAgent($query, $data['agent']);
    $query = $this->whereRegistration($query, $data['registration']);
    $query = $this->whereSince($query, $data['since']);

    return $query;
  }

  /**
   * Extends a query to match a single document using the given data.
   * @param Builder $query Query to extend.
   * @param AssocArray $data Data from the request.
   * @return Builder
   */
  protected function constructShowQuery(Builder $query, array $data) {
    if (!isset($data['activityId'])) throw new \Exception(
      'Missing activityId'
    );
    if (!isset($data[static::$document_identifier])) throw new \Exception(
      'Missing '.static::$document_identifier
    );
    $query = $query->where('documentType', static::$document_type);
    $query = $query->where('activityId', $data['activityId']);
    $query = $query->where(static::$document_identifier, $data[static::$document_identifier]);
    $query = $this->whereAgent($query, $data['agent']);
    $query = $this->whereRegistration($query, $data['registration']);

    return $query;
  }

  private function checkETag() {
    return; // State API should not check ETags (issues/493).
  }

  protected function validateDestroy(array $data) {
    return; // State API should allow multiple deletion.
  }

  /**
   * Validates the data from the request.
   * @param AssocArray $data Data from the request.
   */
  protected function validateData(array $data) {
    if ($data['activityId'] !== null) Helpers::validateAtom(new \Locker\XApi\IRI($data['activityId']));
    if ($data['agent'] !== null) Helpers::validateAtom(new \Locker\XApi\Agent($data['agent']));
    if ($data['since'] !== null) Helpers::validateAtom(new \Locker\XApi\Timestamp($data['since']));
    if ($data['registration'] !== null) Helpers::validateAtom(new \Locker\XApi\UUID($data['registration']));
    if ($data['updated'] !== null) Helpers::validateAtom(new \Locker\XApi\Timestamp($data['updated']));
    if ($data['method'] !== null) Helpers::validateAtom(new \Locker\XApi\String($data['method']));
  }

  /**
   * Gets and validates the data.
   * @param AssocArray $data Data from the request.
   * @return AssocArray
   */
  protected function getData(array $data) {
    if ($data['agent'] !== null) $data['agent'] = json_decode($data['agent']);
    $data = parent::getData($data);
    return $data;
  }
}
