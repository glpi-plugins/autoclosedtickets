<?php
class PluginAutoclosedticketsTicket extends CommonDBTM
{
  public static function canCreate()
  {
    return self::canUpdate();
  }

  public static function canPurge()
  {
    return self::canUpdate();
  }
  static function showCheckBoxITILFollowup  ($params)
  {
    //Логика отрисовки кнопки автозакрытия в форме коментария
      global $DB, $CFG_GLPI;

      if (strpos($_SERVER['REQUEST_URI'], "ticket.form.php") !== false && isset($_GET['id']))
      {
        if (isset($params['item']) && $params['item'] instanceof ITILFollowup)
        {

            $ticket_close = new self();
            //Если признак автозакрытия уже есть то возврат
            if($ticket_close = current($ticket_close->find(['ticket_id' => $_GET['id']])))
            {
              return;
            }
        //    file_put_contents(GLPI_ROOT.'/tmp/buffer.txt',PHP_EOL.PHP_EOL."[".date("Y-m-d H:i:s")."] ". json_encode($ticket_close,JSON_UNESCAPED_UNICODE), FILE_APPEND);
            // Получаем ID текущего пользователя
            $current_user_id = Session::getLoginUserID();

            // Проверяем, является ли пользователь назначенным техником
            $is_assigned = false;
            $ticket = new Ticket ();
            if ($ticket->getFromDB($_GET['id'])) {
                $technicians = $ticket->getUsers(CommonITILActor::ASSIGN);

                foreach ($technicians as $technician) {
                    if ($technician['users_id'] == $current_user_id) {
                        $is_assigned = true;
                        break;
                    }
                }
            }
          //  file_put_contents(GLPI_ROOT.'/tmp/buffer.txt',PHP_EOL.PHP_EOL."[".date("Y-m-d H:i:s")."] ". json_encode($technicians,JSON_UNESCAPED_UNICODE), FILE_APPEND);
            //Если текущий пользователь не является исполнителем то возврат
            if(!$is_assigned)
            {
              return;
            }
            
            $html =  addslashes('<span class="bg-blue-lt d-inline-flex align-items-center ps-2" title="" data-bs-toggle="tooltip" data-bs-placement="top" style="border-left-style: solid;" role="button" data-bs-original-title="Автозакрытие заявки">'.
                           '<i class="fas fa-pause me-2"></i>'.
                           '<label class="form-check form-switch mt-2">'.
                              '<input type="hidden" name="closed_ticket_auto" value="0">'.
                              '<input type="checkbox" name="closed_ticket_auto" value="1" class="form-check-input collapsed" id="" role="button" data-bs-toggle="collapse" data-bs-target="" aria-expanded="false">'.
                          ' </label>'.
                        '</span>') ;

            echo Html::scriptBlock(<<<JAVASCRIPT

              $(document).ready(function(){
                $('.itilfollowup').find('.input-group-text').append("{$html}")
                // Инициализация всех тултипов Bootstrap 5
                 var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                 var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                   return new bootstrap.Tooltip(tooltipTriggerEl);
                 });
              })

              JAVASCRIPT
            );
        }
      }
  }

  static function showTimelineClose  ($params)
  {
      //Логика отображения признакак о закрытии заявки в коментарии
      $pluginTicket = new self();
      //Если нет запись признака автозакрытия заявки то возврат.
      if(!$ticket_close = current($pluginTicket->find(['ticket_id' => $_GET['id']])))
      {
        return;
      }
      $timeline_id = 'ITILFollowup_'.$ticket_close['followup_id'];
      //Если есть признак автозакрытия и такой коментарий действительно существуют то отрисовываем сообщение
      if (isset($params['timeline']) && isset($params['timeline'][$timeline_id]))
      {
      $html =  addslashes('<span class="badge bg-red-lt" title="Автозакрытие">'.
                  '<i class="fa-solid fa-triangle-exclamation"></i>'.
                  'Обращение закроется автоматически через 48 часов если не поступит ответ и обращение не сменит статус на "Приостановка"'.
               '</span>') ;

      echo Html::scriptBlock(<<<JAVASCRIPT

        $(document).ready(function(){
          let timeline_id = '{$timeline_id}';
          $('#'+timeline_id).find('.timeline-badges').append("{$html}")
          // Инициализация всех тултипов Bootstrap 5
        })

        JAVASCRIPT
      );
    }
  }

}
