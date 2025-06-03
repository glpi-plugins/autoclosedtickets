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
    //Логика проверки автозакрытия обращений
    $pluginTicket = new PluginAutoclosedticketsTicket();
    $pluginTickets = $pluginTicket->find();
    //Проверка наличия признака автозакрытия по обращениям
    if(is_array($pluginTickets) && count($pluginTickets) != 0)
    {
      $Calendar = new Calendar();
      $Calendar = $Calendar->find(['name' => 'Default']);
      $calendar_id = current($Calendar)['id'];
      $totalTime = 0;
      foreach ($pluginTickets as $key => $value) {
        $created = $value['created'];
        $totalTime =  self::getHoursForCalendar($created,date("Y-m-d H:i:s"),$calendar_id);//Подсчет прошедего времени в часах до одной десятой
        //Если прошло времени с момента установки признака больше 2 часов то то удаляем признак и меняем статус на решено и отправляем решение что обращение закрыто автоматически
        if($totalTime > 2)
        {
          $solution = new ITILSolution();

          // Данные для добавления
          $input = [
              'itemtype'  => 'Ticket',     // Тип объекта (заявка)
              'items_id'  => $value['ticket_id'],   // ID заявки
              'content'   => 'Обращение закрыто автоматически', // Текст решения
              // 'solutiontypes_id' => 1,   // Опционально: ID типа решения (если используется)
          ];
          $solution->add($input);
        }
      }

      file_put_contents(GLPI_ROOT.'/tmp/buffer.txt',PHP_EOL.PHP_EOL."[".date("Y-m-d H:i:s")."] ". json_encode($totalTime,JSON_UNESCAPED_UNICODE), FILE_APPEND);
    }

  }
  public static function getHoursForCalendar($date1 = null,$date2 =null,$calendars_id = false)
  {
    if(!$calendars_id)
    {
      return false;
    }
    $getSlaCalendarsSegments = self::getSlaCalendarsSegments($calendars_id);
    $period = new DatePeriod(
             new DateTime($date1),
             new DateInterval('P1D'),
             new DateTime($date2)
        );
    $date1 = new DateTime($date1);
    $date2 = new DateTime($date2);
    if($date1 == $date2)
    {
      return '0';
    }
    $weekWork = [];
    foreach ($getSlaCalendarsSegments as $val) {
      $weekWork[$val['day']] = $val;
    }
    $startWeekDay = 0;
    $endWeekDay   = 0;
    $data = [];
    $data['holidays'] = self::getHolidays($calendars_id);
    if($date1->format("Y-m-d") == $date2->format("Y-m-d"))
    {
      $data['currents'][] = ['timeStart'=>$period->getStartDate()->format('Y-m-d H:i:s'),'w'=>$period->getStartDate()->format('w')];
      $startWeekDay = $date1->format("Y-m-d");
    }

    $i = 0;

    foreach ($period as $key => $value)
    {

      foreach ($data['holidays'] as  $holiday) {
        if($holiday == $value->format('Y-m-d'))
        {
          continue 2;
        }
      }
      if(!self::isWeekDayWork($calendars_id,$value->format('w')))
      {

        continue;
      }
      if(!$i)
      {
        $data['currents'][] = ['timeStart'=>$value->format('Y-m-d H:i:s'),'w'=>$value->format('w')];
        $startWeekDay = $value->format("Y-m-d");

      }
      else
      {
        $data['currents'][] = ['time'=>$value->format('Y-m-d H:i:s'),'w'=>$value->format('w')];
      }
      $i++;

    }
    if(self::isWeekDayWork($calendars_id,$period->getEndDate()->format('w')))
    {
      if(strtotime($date1->format('H:i:s')) > strtotime($date2->format('H:i:s')))
        {
          $data['currents'][] = ['timeEnd'=>$date2->format('Y-m-d H:i:s'),'w'=>$date2->format('w')];
            $endWeekDay   = $date2->format('Y-m-d');
        }
      elseif(strtotime($date1->format('H:i:s')) <= strtotime($date2->format('H:i:s')))
        {

           $last = end($data['currents']);
           if(explode(" ",reset($last))[0] == $date2->format("Y-m-d"))
           {
             array_pop($data['currents']);
           }
          $time = $date2->format('H:i:s');
          $data['currents'][] = ['timeEnd'=>$date2->format("Y-m-d $time"),'w'=>$date2->format('w')];
          $endWeekDay   = $date2->format('Y-m-d');
        }
      }
     else
      {
        //$d = explode(" ",end($data['currents'])['d'])[0];
        $w = end($data['currents'])['w'];
        if(count($data['currents']) > 1)
         {
            $lastDate =  array_pop($data['currents']);

            $lastDate = explode(" ",$lastDate['time'])[0];
         }
       else
        {
            $lastDate = $date1->format("Y-m-d");
        }

        $data['currents'][] =  ['timeEnd'=>"$lastDate 18:00:00","w"=>$w];
        $endWeekDay   = $lastDate;
     }



     if(!isset($data['currents'][0]['timeStart']))
     {
       array_unshift($data['currents'],['timeStart'=>$date1->format("Y-m-d H:i:s"),'w'=>$date1->format('w')]);
       $startWeekDay = $date1->format("Y-m-d");
     }
    $hours = 0;
    foreach ($data['currents'] as $key => $value) {
      $timeWork = [];
  /*    foreach (self::getSlaCalendarsSegments($calendars_id) as $segment)
      {
          if($segment['day'] == $value['w'])
          {
            $timeWork = ['begin'=>$segment['begin'],'end'=>$segment['end']];
          }

      }*/
      if(isset($weekWork[$value['w']]))
      {
        $timeBegin = strtotime($weekWork[$value['w']]['begin']);
        $timeEnd = strtotime($weekWork[$value['w']]['end']);
      }
      else
      {
        continue;
      }


        if(isset($value['timeStart']))
        {
            $time = strtotime(explode(" ",$value['timeStart'])[1]);
           if(($time >= $timeBegin && $time <= $timeEnd) && $startWeekDay == $endWeekDay)
           {
            $hours += $time;

           }
           elseif(($time >= $timeBegin && $time <= $timeEnd) && $startWeekDay != $endWeekDay)
           {
             $hours += ($timeEnd - $time);

           }
           elseif($time < $timeBegin && $startWeekDay == $endWeekDay)
           {
             $hours = $timeBegin;
           }
           elseif($time < $timeBegin && $startWeekDay != $endWeekDay)
           {
             $hours = ($timeEnd - $timeBegin);
           }

        }

        if(isset($value['time']))
        {
          $time = strtotime(explode(" ",$value['time'])[1]);

          $hours += ($timeEnd - $timeBegin);

        }
        if(isset($value['timeEnd']))
        {//die($startWeekDay."_".$endWeekDay." ".$hours/60/60);
          $time = strtotime(explode(" ",$value['timeEnd'])[1]);
         if(($time >= $timeBegin && $time <= $timeEnd) && $startWeekDay == $endWeekDay)
         {
          $hours = ($time - $hours);
         }
         elseif(($time >= $timeBegin && $time <= $timeEnd) && $startWeekDay != $endWeekDay)
         {

           if($hours)
           {
             $hours += ($time - $timeBegin);
           }
           else
           {
             $hours = ($time - $timeBegin);
           }

         }
         elseif($time > $timeEnd && $startWeekDay == $endWeekDay)
         {
           if($hours)
           {
             $hours = ($timeEnd - $hours);
           }

         }
         elseif($time > $timeEnd && $startWeekDay != $endWeekDay)
         {
           $hours += ($timeEnd - $timeBegin);
         }
         elseif($time < $timeBegin && $startWeekDay == $endWeekDay)
         {
           $hours = 0;
         }
        }
    }
    if($hours)$hours = round($hours/60/60,1);
    return "$hours";//$date2->format('w');
  }
  public static function getSlaCalendarsSegments($calendars_id)
  {
    global $DB;
    $request = $DB->request([
      'SELECT'=>['day','begin','end'],
      'FROM' => 'glpi_calendarsegments',
      'WHERE'=>['calendars_id'=>$calendars_id]
    ]);
    $data = [];
    foreach ($request as $row)
    {
      $data[] = $row;
    }
    return $data;
  }
  public static function getHolidays($calendars_id)
  {
    global $DB;
    $holidays_ids = $DB->request([
      'SELECT'=>['holidays_id'],
      'FROM' => 'glpi_calendars_holidays',
      'WHERE'=>['calendars_id'=>$calendars_id]
    ]);
    $data = [];
    foreach ($holidays_ids as $row)
    {
      $data[] = $row['holidays_id'];
    }
    $holidays = $DB->request([
      //'SELECT'=>['holidays_id'],
      'FROM' => 'glpi_holidays',
      'WHERE'=>['id'=>$data]
    ]);
    $data = [];
    foreach ($holidays as $key => $value)
    {
      $period_holidays = new DatePeriod(
       new DateTime($value['begin_date']),
       new DateInterval('P1D'),
       new DateTime($value['end_date'])
        );
      foreach ($period_holidays as $value) {

        $data[] = $value->format('Y-m-d');
      }
      $data[] = $period_holidays->getEndDate()->format('Y-m-d');
    }

    return $data;
  }
  public static function isWeekDayWork($calendars_id,$week_id)
  {
    $weeks = self::getSlaCalendarsSegments($calendars_id);
    foreach ($weeks as $row)
    {
      if($row['day']==$week_id)
      {
        return true;
      }
    }
    return false;
  }
}
