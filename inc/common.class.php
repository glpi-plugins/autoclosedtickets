<?php
class PluginAutoclosedticketsCommon extends CommonDBTM
{
  public static function preItemITILFollowupAdd(CommonDBTM $item)

  {
    //Догика перед отрпавкй ответа
    $user_id = Session::getLoginUserID();

    if ($item->fields['itemtype'] != 'Ticket')
    {
        return;
    }

    $ticket_user = new Ticket_User();
    $initiators = $ticket_user->find([
        'tickets_id' => $item->fields['items_id'],
        'users_id'   => $user_id,
        'type'       => Ticket_User::REQUESTER
    ]);
    //если пользователь не является инициатором завки то пропускаем
    if(!current($initiators))
    {
      return;
    }
    $pluginTicket = new PluginAutoclosedticketsTicket();
    $ticket_auto_close = current($pluginTicket->find(['ticket_id' => $item->fields['items_id']]));
    //если в завяки нет признака автозакртытия пропускаем
    if(!$ticket_auto_close)
    {
      return;
    }
    //устанавливаем статус в работе
    $item->input['_status_not_change_ticket'] = "off";
    $item->input['_status_current_ticket'] = Ticket::PLANNED;
  //  die(json_encode($item,JSON_UNESCAPED_UNICODE));return;
     file_put_contents(GLPI_ROOT.'/tmp/buffer.txt',PHP_EOL.PHP_EOL."[".date("Y-m-d H:i:s")."] ". json_encode($item,JSON_UNESCAPED_UNICODE), FILE_APPEND);
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
