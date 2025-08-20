<?php
namespace Watermoss\Network;

class RakNet {
    public const MAGIC = "\x00\xff\xff\x00\xfe\xfe\xfe\xfe\xfd\xfd\xfd\xfd\x12\x34\x56\x78";
    public const UNCONNECTED_PING = "\x01"; // client->server
    public const UNCONNECTED_PONG = "\x1c"; // server->client
}
