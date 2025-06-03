<?php
class PluginAutoclosedticketsCommon extends CommonDBTM
{
  public static function preItemITILFollowupAdd(CommonDBTM $item)
  {
  //  die(json_encode($item,JSON_UNESCAPED_UNICODE));
  //   file_put_contents(GLPI_ROOT.'/tmp/buffer.txt',PHP_EOL.PHP_EOL."[".date("Y-m-d H:i:s")."] ". json_encode($item,JSON_UNESCAPED_UNICODE), FILE_APPEND);
    return;
  }
  public static function itemITILFollowupAdd(CommonDBTM $item)
  {
  //  ЛОгика при добавлении коментария к заявке
      //проверяем  признак автозакрытия
      $closed_ticket_auto = false;
      if(isset($item->input['closed_ticket_auto']) && $item->input['closed_ticket_auto'] )
      {
        $closed_ticket_auto = true;
      }
      if(!isset($item->input['_job']))
      {
        return;
      }
      $job = $item->input['_job'];

      $pluginTicket = new PluginAutoclosedticketsTicket();
      $ticket_close = current($pluginTicket->find(['ticket_id' => $job->fields['id']]));
      //если статус равен приостановка и нет записи признака автозакрытия то добавляем запись признака
      if(isset($job->fields['status']) && $job->fields['status'] == 4 && !$ticket_close && $closed_ticket_auto)
      {
        $data = [
        'ticket_id' => $job->fields['id'],
        'followup_id' => $item->fields['id']
      ];
      $pluginTicket->add($data);
      }
      //если статус заявки не равен приостановка и есть запись признака автозакрытия то удаляем признак
      if(isset($job->fields['status']) && $job->fields['status'] != 4 && $ticket_close)
      {
        $pluginTicket->delete([
          'id' => $ticket_close['id']
        ], 1);
      }
    return;
  }

  public static function itemTicketAdd (CommonDBTM $item)
  {
    //Логика обновления заявки 
    //удаляем запись призанака  автозакрытия заявки если статус не равен Приостановка
    if($item->fields['status'] != 4)
    {
      $pluginTicket = new PluginAutoclosedticketsTicket();
      $ticket_close = current($pluginTicket->find(['ticket_id' =>$item->fields['id']]));
      if($ticket_close)
      {
        $pluginTicket->delete([
          'id' => $ticket_close['id']
        ], 1);
      }
    }
    // file_put_contents(GLPI_ROOT.'/tmp/buffer.txt',PHP_EOL.PHP_EOL."[".date("Y-m-d H:i:s")."] ". json_encode($item,JSON_UNESCAPED_UNICODE), FILE_APPEND);
  }
}
