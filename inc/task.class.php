<?php

class PluginAutoclosedticketsTask extends Search
{

  static function cronwatchTickets()
  {
    self::watchTickets();
    return true;
  }

  static function watchTickets()
  {
    $pluginTicket = new PluginAutoclosedticketsTicket();
    $ticket_close = $pluginTicket->find();
    file_put_contents(GLPI_ROOT.'/tmp/buffer.txt',PHP_EOL.PHP_EOL."[".date("Y-m-d H:i:s")."] ". json_encode($ticket_close,JSON_UNESCAPED_UNICODE), FILE_APPEND);
  }
}
