<?php
class PluginAutoclosedticketsCommon extends CommonDBTM
{
  public static function preItemITILFollowupAdd(CommonDBTM $item)

  {
    //Догика перед отрпавкй ответа
    $user_id = Session::getLoginUserID();

    if (isset($item->fields['itemtype']) && $item->fields['itemtype'] != 'Ticket')
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
     //file_put_contents(GLPI_ROOT.'/tmp/buffer.txt',PHP_EOL.PHP_EOL."[".date("Y-m-d H:i:s")."] ". json_encode($item,JSON_UNESCAPED_UNICODE), FILE_APPEND);
    return;
  }

  public static function itemITILFollowupAdd(CommonDBTM $item)
  {
  //  ЛОгика при добавлении коментария к заявке
      //проверяем  признак автозакрытия

      $closed_ticket_auto = false;
      if(isset($item->input['closed_ticket_auto_followup']) && $item->input['closed_ticket_auto_followup'] )
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
      if(isset($job->fields['status']) && $job->fields['status'] == 4 && $ticket_close && $closed_ticket_auto){
        $data = [
            'id'          => $ticket_close['id'], // ОБЯЗАТЕЛЬНО: ID обновляемой строки
            'ticket_id'   => $job->fields['id'],
            'followup_id' => $item->fields['id']
        ];
        $pluginTicket->update($data);
      }
      //если статус заявки не равен приостановка и есть запись признака автозакрытия то удаляем признак
      if(isset($job->fields['status']) && $job->fields['status'] != 4 && $ticket_close)
      {
        self::deleteTcicketClosed($ticket_close['id']);
      }
    return;
  }
  public static function itemITILSolutionAdd(CommonDBTM $item)
  {
  //  ЛОгика при добавлении коментария к заявке
      //проверяем  признак автозакрытия
      $closed_ticket_auto = false;
      if(isset($item->input['action_solution']) && $item->input['action_solution'] == 'closed_ticket_auto_solution' )
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
      //если статус равен решено и нет записи признака автозакрытия то добавляем запись признака
      if(isset($job->fields['status']) && $job->fields['status'] == 5 && !$ticket_close && $closed_ticket_auto)
      {
        $data = [
        'ticket_id' => $job->fields['id'],
        'solution_id' => $item->fields['id']
      ];
      $pluginTicket->add($data);
    }
    if(isset($job->fields['status']) && $job->fields['status'] == 5 && $ticket_close && $closed_ticket_auto){
      $data = [
          'id'          => $ticket_close['id'], // ОБЯЗАТЕЛЬНО: ID обновляемой строки
          'ticket_id'   => $job->fields['id'],
          'solution_id' => $item->fields['id']
      ];
      $pluginTicket->update($data);
    }
      //если статус заявки не равен приостановка и есть запись признака автозакрытия то удаляем признак
      if(isset($job->fields['status']) && $job->fields['status'] != 5 && $ticket_close)
      {
        self::deleteTcicketClosed($ticket_close['id']);
      }
    return;
  }
  /**
   * Метод удаления признака автозакрытия.
   * Он просто удаляет запись в таблице при условии что followup_id и solution_id равны null.
   */
   public static function deleteTcicketClosed($id)
   {
       // Создаем экземпляр объекта
       $pluginTicket = new self();

       // Пытаемся загрузить запись из базы по её ID
       if ($pluginTicket->getFromDB($id)) {

           // Получаем значения полей из загруженной строки
           $followup_id = $pluginTicket->fields['followup_id'];
           $solution_id = $pluginTicket->fields['solution_id'];

           // В GLPI пустые ключи могут храниться как NULL, так и как цифра 0.
           // Функция empty() надежно отловит оба варианта.
           if (/*empty($followup_id) &&*/ empty($solution_id)) {

               // Условия выполнены -> смело удаляем
               $pluginTicket->delete([
                   'id' => $id
               ], 1);

               return true; // Возвращаем true, если удаление прошло
           }
       }

       return false; // Возвращаем false, если запись не найдена или поля не пустые
   }

  public static function itemTicketUpdate (CommonDBTM $item)
  {
    //Логика обновления заявки
    //удаляем запись призанака  автозакрытия заявки если статус не равен Приостановка
    if($item->fields['status'] != 5)
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
