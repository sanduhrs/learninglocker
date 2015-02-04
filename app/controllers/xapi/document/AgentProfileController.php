<?php namespace Controllers\XAPI\Document;

use \LockerRequest as LockerRequest;
use \IlluminateResponse as IlluminateResponse;

class AgentProfileController extends BaseController {
  protected static $document_identifier = 'profileId';
  protected static $document_repo = '\Repos\Document\AgentProfile\EloquentRepository';
  protected static $document_type = 'agentProfile';

  /**
   * Gets the Person that fulfils the given parameters.
   * Note: We don't do any searching we just construct the Person based on the given parameters.
   * https://github.com/adlnet/xAPI-Spec/blob/master/xAPI.md#combined-information-get
   * We should use repo->index($this->getAuthority(), $data), then combine the results into a Person.
   * @return \Illuminate\Http\JsonResponse Person represented in JSON.
   */
  public function search() {
    $agent = (array) LockerRequest::getParams();
    $person = ['objectType' => 'Person'];
    $keys = ['name', 'mbox', 'mbox_sha1sum', 'openid', 'account'];

    foreach ($keys as $key) {
      if (isset($agent[$key])) {
        $person[$key] = [$agent[$key]];
      }
    }

    return IlluminateResponse::json($person, 200, $this->getCORSHeaders());
  }
}

