<?php namespace Controllers\XAPI;

use \IlluminateResponse as IlluminateResponse;
use \IlluminateRequest as IlluminateRequest;
use \LockerRequest as LockerRequest;
use \Repos\Statement\EloquentRepository as StatementRepository;

class StatementController extends BaseController {

  // Sets constants for param keys.
  const STATEMENT_ID = 'statementId';
  const VOIDED_ID = 'voidedStatementId';

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

  protected function store() {
    if (LockerRequest::hasParam(self::STATEMENT_ID)) {
      throw new \Exception(trans('xapi.errors.id_set', [
        'statement_id' => self::STATEMENT_ID
      ]));
    }

    return IlluminateResponse::json($this->createStatements(), 200);
  }

  protected function update() {
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

    return IlluminateResponse::make('', 200);
  }

  protected function destroy() {
    return IlluminateResponse::make('', 405);
  }

  private function getParts() {
    // Gets the content.
    $content = \LockerRequest::getContent();
    $contentType = \LockerRequest::header('content-type');

    // Gets the $mime_type.
    $types = explode(';', $contentType, 2);
    $mime_type = count($types) >= 1 ? $types[0] : $types;

    if ($mime_type == 'multipart/mixed') {
      $components = Attachments::setAttachments($contentType, $content);

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
    $current_url = IlluminateRequest::fullUrl();
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
      $options[$param] = LockerRequest::getParam($param);
    }

    // Gets the statements.
    $statements = (new StatementRepository)->index($this->getAuthority(), $options);

    // Constructs the response.
    return IlluminateResponse::json([
      'more' => $this->getMoreLink((new StatementRepository)->count($this->getAuthority()), $options['limit'], $options['offset']),
      'statements' => $statements
    ], 200, $this->getCORSHeaders());
  }

  private function show($id, $voided) {
    // Gets the statement.
    $statement = (new StatementRepository)->show($this->getAuthority(), $id, $voided);

    // Returns the response.
    if ($statement !== null) {
      return IlluminateResponse::json($statement->statement, 200, $this->getCORSHeaders());
    } else {
      return IlluminateResponse::json(null, 404, $this->getCORSHeaders());
    }
  }

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
    return $this->statement->create(
      $statements,
      $this->getAuthority(),
      $parts['attachments']
    );
  }
}
