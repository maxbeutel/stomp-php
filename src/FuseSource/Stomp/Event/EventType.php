<?php

namespace FuseSource\Stomp\Event;

final class EventType
{
	const FRAME_CONNECTED = 'connected';

	const FRAME_ERROR = 'error';

	const TRANSPORT_ERROR = '__transport/error';
}