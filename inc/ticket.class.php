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
          $rand = rand();
          echo Html::scriptBlock(<<<JAVASCRIPT

            $(document).ready(function(){
               let html = `<div class="form-field row col-12  mb-2">
                             <label class="col-form-label col-2 text-xxl-end" for="dropdown_requesttypes_id_{$rand}">
                                 <i class="fas fa-toolbox fa-fw me-1" title="Действия при отправки коментария"></i>
                             </label>
                             <div class="col-10  field-container">
                                <select name="action_followup" id="followupSelect_{$rand}">
                                  <option value="">------------</option>
                                  <option value="pending_ticket">Приостановка</option>
                                </select>
                             </div>
                           </div>`
              $('.itilfollowup').find('.input-group-text').removeClass('bg-yellow-lt');
              $('.itilfollowup').find('.input-group-text').removeClass('flex-fill');
              $('.itilfollowup').find('.input-group-text').addClass('bg-secondary-lt pt-2');
              $('.itilfollowup').find('.input-group-text').html(html);

              $('#followupSelect_{$rand}').on('change', function() {
                    const selectedValue = $(this).val();
                    $('.itilfollowup').find('input[name=pending]').remove();
                    if(selectedValue == 'pending_ticket')
                    {
                      $('.itilfollowup').find('.input-group-text').append(`<input type="checkbox" name="pending" value="1" style="display:none" checked="">`);
                    }
                });
              // Инициализация всех тултипов Bootstrap 5
               var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
               var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                 return new bootstrap.Tooltip(tooltipTriggerEl);
               });
            })

            JAVASCRIPT
          );
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

            echo Html::scriptBlock(<<<JAVASCRIPT

              $(document).ready(function(){
                 let html = `<div class="form-field row col-12  mb-2">
                               <label class="col-form-label col-2 text-xxl-end" for="dropdown_requesttypes_id_{$rand}">
                                   <i class="fas fa-toolbox fa-fw me-1" title="Действия при отправки коментария"></i>
                               </label>
                               <div class="col-10  field-container">
                                  <select name="action_followup" id="followupSelect_{$rand}">
                                    <option value="">------------</option>
                                    <option value="pending_ticket">Приостановка</option>
                                    <option value="closed_ticket_auto">Автозакрытие</option>
                                  </select>
                               </div>
                             </div>`
                //$('.itilfollowup').find('.input-group-text').append("{$html}")
                $('.itilfollowup').find('.input-group-text').removeClass('bg-yellow-lt')
                $('.itilfollowup').find('.input-group-text').addClass('bg-secondary-lt pt-2')
                $('.itilfollowup').find('.input-group-text').html(html)

                $('#followupSelect_{$rand}').on('change', function() {
                      const selectedValue = $(this).val();
                      $('.itilfollowup').find('input[name=pending]').remove();
                      $('.itilfollowup').find('input[name=closed_ticket_auto]').remove();
                      $('.itilfollowup').find('.input-group-text').removeClass('flex-fill');
                      if(selectedValue == 'pending_ticket')
                      {
                        $('.itilfollowup').find('.input-group-text').append(`<input type="checkbox" name="pending" value="1" style="display:none" checked="">`);
                      }
                      if(selectedValue == 'closed_ticket_auto')
                      {
                        $('.itilfollowup').find('.input-group-text').append(`<input type="checkbox" name="pending" value="1" style="display:none" checked="">`);
                        $('.itilfollowup').find('.input-group-text').append(`<input type="checkbox" name="closed_ticket_auto" value="1" style="display:none" checked="">`);
                      }
                  });
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
                  'Обращение закроется автоматически через 48 часов если не поступит ответ и обращение не сменит статус "Приостановка"'.
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
