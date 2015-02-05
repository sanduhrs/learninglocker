<?php namespace Repos\Document\AgentProfile;

use \Jenssegers\Mongodb\Eloquent\Builder as Builder;
use \Repos\Document\EloquentRepository as DocumentRepository;
use \Helpers\Helpers as Helpers;
use \Models\Authority as Authority;

class EloquentRepository extends DocumentRepository {
  protected static $document_type = 'agentProfile';
  protected static $document_identifier = 'profileId';

  protected static $ap_props = [
    'profileId',
    'agent',
  ];
  protected static $data_props = [
    'profileId' => null,
    'agent' => null,
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
    if (!isset($data['agent'])) throw new \Exception(
      'Missing agent'
    );
    $query = $query->where('documentType', static::$document_type);
    $query = $this->whereAgent($query, $data['agent']);
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
    if (!isset($data['agent'])) throw new \Exception(
      'Missing agent'
    );
    if (!isset($data[static::$document_identifier])) throw new \Exception(
      'Missing '.static::$document_identifier
    );

    $query = $query->where('documentType', static::$document_type);
    $query = $query->where(static::$document_identifier, $data[static::$document_identifier]);
    $query = $this->whereAgent($query, $data['agent']);

    return $query;
  }

  /**
   * Validates the data from the request.
   * @param AssocArray $data Data from the request.
   */
  protected function validateData(array $data) {
    if ($data['agent'] !== null) Helpers::validateAtom(new \Locker\XApi\Agent($data['agent']));
    if ($data['profileId'] !== null) Helpers::validateAtom(new \Locker\XApi\String($data['profileId']));
    if ($data['since'] !== null) Helpers::validateAtom(new \Locker\XApi\Timestamp($data['since']));
    if ($data['ifMatch'] !== null) Helpers::validateAtom(new \Locker\XApi\Sha1($data['ifMatch']));
    if ($data['ifNoneMatch'] !== null && $data['ifNoneMatch'] !== '*') Helpers::validateAtom(new \Locker\XApi\Sha1($data['ifNoneMatch']));
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

  /**
   * Constructs a Person that fulfils the given parameters.
   * https://github.com/adlnet/xAPI-Spec/blob/master/xAPI.md#combined-information-get
   * @return \stdClass.
   */
  public function getPerson(Authority $authority, array $data) {
    $agents = array_column($this->indexBuilder($authority, $data)->get()->toArray(), 'agent');
    $profile = (object) [
      'objectType' => 'Person',
      'name' => array_unique(array_column($agents, 'name')),
      'mbox' => array_unique(array_column($agents, 'mbox')),
      'mbox_sha1sum' => array_unique(array_column($agents, 'mbox_sha1sum')),
      'openid' => array_unique(array_column($agents, 'openid')),
      'accounts' => array_unique(array_column($agents, 'accounts'))
    ];
    return Helpers::replaceHTMLDots($profile);
  }
}
