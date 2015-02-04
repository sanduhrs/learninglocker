<?php namespace Repos\Document\ActivityProfile;

use \Jenssegers\Mongodb\Eloquent\Builder as Builder;
use \Repos\Document\EloquentRepository as DocumentRepository;
use \Helpers\Helpers as Helpers;

class EloquentRepository extends DocumentRepository {
  protected static $document_type = 'activityProfile';
  protected static $document_identifier = 'profileId';

  protected static $ap_props = [
    'profileId',
    'activityId'
  ];
  protected static $data_props = [
    'profileId' => null,
    'activityId' => null,
    'since' => null,
    'content_info' => null,
    'ifMatch' => null,
    'ifNoneMatch' => null,
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

    return $query;
  }

  /**
   * Validates the data from the request.
   * @param AssocArray $data Data from the request.
   */
  protected function validateData(array $data) {
    if ($data['activityId'] !== null) Helpers::validateAtom(new \Locker\XApi\IRI($data['activityId']));
    if ($data['profileId'] !== null) Helpers::validateAtom(new \Locker\XApi\String($data['profileId']));
    if ($data['since'] !== null) Helpers::validateAtom(new \Locker\XApi\Timestamp($data['since']));
    if ($data['ifMatch'] !== null) Helpers::validateAtom(new \Locker\XApi\Sha1($data['ifMatch']));
    if ($data['ifNoneMatch'] !== null && $data['ifNoneMatch'] !== '*') Helpers::validateAtom(new \Locker\XApi\Sha1($data['ifNoneMatch']));
    if ($data['updated'] !== null) Helpers::validateAtom(new \Locker\XApi\Timestamp($data['updated']));
    if ($data['method'] !== null) Helpers::validateAtom(new \Locker\XApi\String($data['method']));
  }
}
