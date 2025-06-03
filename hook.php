<?php

/**
 * -------------------------------------------------------------------------
 * autoclosedtickets plugin for GLPI
 * Copyright (C) 2025 by the autoclosedtickets Development Team.
 * -------------------------------------------------------------------------
 *
 * MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * --------------------------------------------------------------------------
 */

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_autoclosedtickets_install()
{
  global $DB;
  $version = plugin_version_autoclosedtickets();
  //создать экземпляр миграции с версией
  // Параметры автоматического действия
   $actionTitle = "Автозакрытие заявок";
   $actionClass = "PluginAutoclosedticketsTask"; // Имя класса
   $actionMethod = "watchTickets"; // Метод класса

   // Регистрация действия
   CronTask::Register(
       $actionClass, // Объект (класс)
       $actionMethod, // Метод
       60, // Интервал (в минутах)
       [
           'comment'   => $actionTitle,
           'mode'      => 2, // Запуск по расписанию
           'parameter' => null,
       ]
   );
      $migration = new Migration($version['version']);
      //Create table only if it does not exists yet!
      if (!$DB->tableExists('glpi_plugin_autoclosedtickets_tickets')) {
        // Запрос на создание таблицы с исправлениями
        $query = 'CREATE TABLE IF NOT EXISTS `glpi_plugin_autoclosedtickets_tickets` (
              `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `ticket_id`    INT UNSIGNED   NOT NULL,
              `followup_id`    INT UNSIGNED   NOT NULL,
              `created` TIMESTAMP NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY    (`id`)
           ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;';

        $DB->queryOrDie($query, $DB->error());
    }

    //создать экземпляр миграции с версией
    $migration = new Migration($version['version']);
    //execute the whole migration
    $migration->executeMigration();
    return true;
}

/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_autoclosedtickets_uninstall()
{
    CronTask::Unregister('PluginAutoclosedticketsTask','watchTickets');
    return true;
}
