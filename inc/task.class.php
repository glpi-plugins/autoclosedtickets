<?php

class PluginAutoclosedticketsTask extends CommonDBTM
{
    static function cronwatchTickets()
    {
        self::watchTickets();
        return true;
    }

    static function watchTickets()
    {
        global $DB;

        // 1. Безопасная инициализация окружения для Cron
        if (PHP_SAPI == 'cli' || !isset($_SESSION['glpiID'])) {
            $userId = 2; // ID технического пользователя (обычно glpi)

            $user = new User();
            if ($user->getFromDB($userId)) {
                $_SESSION['glpiID']               = $userId;
                $_SESSION['glpiname']             = $user->fields['name'];
                $_SESSION['glpiactiveentities']   = [0]; // Корневая сущность
                $_SESSION['glpishowallentities']  = 1;
                $_SESSION['glpiactive_entity']    = 0;

                // Надежно получаем группы прямым запросом к БД (замена удаленному User::getGroups)
                $groups = [];
                $groupIterator = $DB->request([
                    'SELECT' => 'groups_id',
                    'FROM'   => 'glpi_groups_users',
                    'WHERE'  => ['users_id' => $userId]
                ]);
                foreach ($groupIterator as $row) {
                    $groups[] = $row['groups_id'];
                }
                $_SESSION['glpigroups'] = $groups;

                // Надежно получаем профили прямым запросом
                $profileIterator = $DB->request([
                    'SELECT' => 'profiles_id',
                    'FROM'   => 'glpi_profiles_users',
                    'WHERE'  => ['users_id' => $userId]
                ]);
                if (count($profileIterator)) {
                    $firstProfile = $profileIterator->current();
                    $_SESSION['glpiactiveprofile'] = $firstProfile['profiles_id'];
                }
            } else {
                Toolbox::logError("AutoclosedTickets: User ID $userId not found.");
                return false;
            }
        }

        // 2. Логика проверки автозакрытия обращений
        $pluginTicket = new PluginAutoclosedticketsTicket();
        $pluginTickets = $pluginTicket->find();

        if (is_array($pluginTickets) && count($pluginTickets) > 0) {

            $cal = new Calendar();
            // Ищем дефолтный календарь
            $calIterator = $cal->find(['name' => 'Default']);

            if (count($calIterator)) {
                $calendar_id = current($calIterator)['id'];
                $cal->getFromDB($calendar_id); // Загружаем объект календаря для расчетов

                $now = date("Y-m-d H:i:s");

                foreach ($pluginTickets as $key => $value) {
                    $created = $value['created'];

                    // Штатный метод GLPI: получаем рабочее время в секундах
                    $activeSeconds = $cal->getActiveTimeBetween($created, $now);
                    // Переводим в часы до одной десятой
                    $totalTime = round($activeSeconds / 3600, 1);
                    // --- ОТЛАДКА В ЛОГ ---
                    // Функция print_r(..., true) позволяет безопасно выводить как обычные числа/строки, так и массивы
                    Toolbox::logInFile('php-errors', "AutoclosedTickets | Текущее время: $now | Дата создания: $created | Прошло рабочих часов: " . print_r($totalTime, true) . "\n");
                    // Если прошло времени больше заданного (в коде 0.1, в твоем комменте было 2 часа)
                    if ($totalTime > 0.05) {
                      // Инициализируем класс Ticket и принудительно меняем статус на "Закрыто"
                          $ticket = new Ticket();
                          $ticket->update([
                              'id'     => $value['ticket_id'], // ID самой заявки
                              'status' => Ticket::CLOSED       // Константа статуса "Закрыто"
                          ]);
                        // Добавляем решение и удаляем признак
                        $pluginTicket->delete(['id' => $value['id']], 1);
                    }
                }
            }
        }
    }
}
