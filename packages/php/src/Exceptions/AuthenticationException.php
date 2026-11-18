<?php

namespace Avatarak\Exceptions;

/** 401: the API key is missing, wrong, or its site was deleted. */
class AuthenticationException extends ApiException {}
