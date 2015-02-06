<?php namespace Controllers\XAPI;

use \LockerRequest as LockerRequest;
use \IlluminateResponse as IlluminateResponse;
use \IlluminateRequest as IlluminateRequest;
use \Repos\Statement\EloquentRepository as StatementRepository;
use \Helpers\Exceptions\NotFound as NotFoundException;
use \Helpers\Exceptions\Conflict as ConflictException;
use \Helpers\Attachments as AttachmentsHelper;
use \Helpers\Helpers as Helpers;
use \Locker\XApi\IMT as XAPIIMT;

class StatementController extends BaseController {

  // Sets constants for param keys.
  const STATEMENT_ID = 'statementId';
  const VOIDED_ID = 'voidedStatementId';

  /**
   * Calls either index or show depending on the request.
   * @return \Illuminate\Http\ResponseTrait Result of the index/show.
   */
  protected function get() {
    // Gets the identifiers.
    $statement_id = LockerRequest::getParam(self::STATEMENT_ID);
    $voided_id = LockerRequest::getParam(self::VOIDED_ID);

    // Selects the method to be called.
    if ($statement_id !== null && $voided_id !== null) {
      throw new \Exception(trans('xapi.errors.both_ids', [
        'statement_id' => self::STATEMENT_ID,
        'voided_id' => self::VOIDED_ID
      ]));
    } else if ($statement_id !== null && $voided_id === null) {
      return $this->show($statement_id, false);
    } else if ($statement_id === null && $voided_id !== null) {
      return $this->show($voided_id, true);
    } else {
      return $this->index();
    }
  }

  /**
   * GETs all of the statements that fulfil the given request parameters.
   * @return \Illuminate\Http\JsonResponse Response containing a more link and an array of statements.
   */
  private function index() {
    // Defines the parameter keys.
    $params = [
      'agent', 'activity', 'verb', 'registration', 'since', 'until', 'active', 'voided',
      'related_activities', 'related_agents', 'ascending', 'format', 'offset', 'limit',
      'attachments'
    ];

    // Gets the parameter values.
    $options = [];
    foreach ($params as $param) {
      $options[$param] = LockerRequest::getParam($param, null);
    }

    // Adds langs.
    $options['langs'] = explode(',', IlluminateRequest::header('Accept-Language', ''));

    // Gets the statements.
    list($statements, $count) = (new StatementRepository)->index($this->getAuthority(), $options);

    // Constructs the response.
    return IlluminateResponse::json([
      'more' => $this->getMoreLink(
        $count,
        $options['limit'],
        $options['offset']
      ),
      'statements' => $statements
    ], 200, $this->getCORSHeaders());
  }

  /**
   * GETs a single statement that matches the given id.
   * @param String $id UUID of the statement to be returned.
   * @param Boolean $voided Determines if the statement to be returned has been voided.
   * @return \Illuminate\Http\ResponseTrait Statement in JSON form.
   */
  private function show($id, $voided) {
    if (array_diff(array_keys(
      LockerRequest::getParams()),
      [self::STATEMENT_ID, self::VOIDED_ID]
    ) != []) throw new \Exception(
      'Invalid params'
    );

    try {
      $statement = (new StatementRepository)->show($this->getAuthority(), $id, $voided);
      return IlluminateResponse::json($statement->statement, 200, $this->getCORSHeaders());
    } catch (NotFoundException $ex) {
      return IlluminateResponse::make('', 404, $this->getCORSHeaders());
    }
  }

  /**
   * Stores (POSTs) statements.
   * @return \Illuminate\Http\JsonResponse Result of storing the statements.
   */
  protected function store() {
    Helpers::validateAtom(new XAPIIMT(LockerRequest::header('Content-Type')));

    if (LockerRequest::hasParam(self::STATEMENT_ID)) {
      throw new \Exception(trans('xapi.errors.id_set', [
        'statement_id' => self::STATEMENT_ID
      ]));
    }

    try {
      return IlluminateResponse::json($this->createStatements(), 200, $this->getCORSHeaders());
    } catch (ConflictException $ex) {
      return $this->errorResponse($ex, 409);
    }
  }

  /**
   * Inserts (PUTs) a statement.
   * @return \Illuminate\Http\ResponseTrait Result of the inserting a statement.
   */
  protected function update() {
    Helpers::validateAtom(new XAPIIMT(LockerRequest::header('Content-Type')));

    try {
      $this->createStatements(function ($statements) {
        $statement_id = \LockerRequest::getParam(self::STATEMENT_ID);

        // Returns a error if identifier is not present.
        if (!$statement_id) throw new \Exception(
          trans('xapi.errors.required', [
            'field' => self::STATEMENT_ID
          ])
        );

        // Adds the ID to the statement.
        $statements[0]->id = $statement_id;

        return $statements;
      });

      return IlluminateResponse::make('', 204);
    } catch (ConflictException $ex) {
      return $this->errorResponse($ex, 409);
    }
  }

  /**
   * Returns a "method not supported" response because you can't DELETE statements.
   * @return \Illuminate\Http\ResponseTrait
   */
  protected function destroy() {
    return IlluminateResponse::make('', 405);
  }

  /**
   * Gets the content and attachments from the request.
   * @return AssocArray
   */
  private function getParts() {
    // Gets the content.
    $content = \LockerRequest::getContent();
    $contentType = \LockerRequest::header('content-type');

    // Gets the $mime_type.
    $types = explode(';', $contentType, 2);
    $mime_type = count($types) >= 1 ? $types[0] : $types;

    if ($mime_type == 'multipart/mixed') {
      $components = AttachmentsHelper::setAttachments($contentType, $content);

      // Validates components.
      if (empty($components)) throw new \Exception(
        trans('xapi.errors.formatting')
      );
      if (!isset($components['attachments'])) throw new \Exception(
        trans('xapi.errors.no_attachment')
      );

      $content = $components['body'];
      $attachments = $components['attachments'];
    } else {
      $attachments = '';
    }

    return [
      'content' => $content,
      'attachments' => $attachments
    ];
  }

  /**
   * Constructs the "more link" for a statement response.
   * @param Integer $total Number of statements that can be returned for the given request parameters.
   * @param Integer $limit Number of statements to be outputted in the response.
   * @param Integer $offset Number of statements being skipped.
   * @return String A URL that can be used to get more statements for the given request parameters.
   */
  private function getMoreLink($total, $limit, $offset) {
    $no_offset = $offset === null;

    // Uses defaults.
    $total = $total ?: 0;
    $limit = $limit ?: 100;
    $offset = $offset ?: 0;

    // Calculates the $next_offset.
    $next_offset = $offset + $limit;
    if ($total <= $next_offset) return '';

    // Changes (when defined) or appends (when undefined) offset.
    //$current_url = IlluminateRequest::fullUrl();

    $query = IlluminateRequest::getQueryString();
    $statement_route = \URL::route('xapi.statement', [], false);
    $current_url = $query ? $statement_route.'?'.$query : $statement_route;

    if (!$no_offset) {
      return str_replace(
        'offset=' . $offset,
        'offset=' . $next_offset,
        $current_url
      );
    } else {
      $separator = strpos($current_url, '?') !== False ? '&' : '?';
      return $current_url . $separator . 'offset=' . $next_offset;
    }
  }

  /**
   * Creates statements from the content of the request.
   * @param Callable|null $modifier A function that modifies the statements before storing them.
   * @return AssocArray Result of storing the statements.
   */
  private function createStatements(Callable $modifier = null) {
    // Gets parts of the request.
    $parts = $this->getParts();
    $content = $parts['content'];

    // Decodes $statements from $content.
    $statements = json_decode($content);
    if ($statements === null && $content != 'null' && $content != '') {
      throw new \Exception(trans('xapi.errors.json', [
        'value' => $content
      ]));
    }

    // Ensures that $statements is an array.
    if (!is_array($statements)) {
      $statements = [$statements];
    }

    // Runs the modifier if there is one and there are statements.
    if (count($statements) > 0 && $modifier !== null) {
      $statements = $modifier($statements);
    }

    // Saves $statements with attachments.
    return (new StatementRepository)->store(
      $this->getAuthority(),
      $statements,
      is_array($parts['attachments']) ? $parts['attachments'] : []
    );
  }
}
