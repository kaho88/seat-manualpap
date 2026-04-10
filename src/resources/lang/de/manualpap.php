<?php

return [
    'pap_added'       => 'PAP erfolgreich vergeben. Operation #:op → Character #:char',
    'operation'       => 'Operation',
    'select_operation'=> 'Bitte Operation auswählen',
    'character_id'    => 'Character ID',
    'ship_type_id'    => 'Ship Type ID',
    'optional'        => 'Optional',
    'value'           => 'PAP Wert',
    'value_hint'      => 'Leer lassen, um den Tag-Quantifier der Operation zu verwenden',
    'submit'          => 'PAP vergeben',
    'cancelled'       => 'Abgesagt',
    'api_title'       => 'API Endpunkt',
    'api_description' => 'PAPs können auch per API Endpunkt eingefügt werden. Verwende deinen SeAT User API Token (X-Token).',
    'api_env_hint'    => 'Der User benötigt die "manualpap.api" Berechtigung in SeAT. Der Token ist der gleiche X-Token der auch für die SeAT API verwendet wird.',

    // Massenimport
    'bulk_result'        => 'Massenimport abgeschlossen: :ok/:total PAPs zu ":op" hinzugefügt. :failed fehlgeschlagen.',
    'bulk_failed_names'  => 'Folgende Characters konnten nicht aufgelöst werden:',
    'bulk_info_title'    => 'So funktioniert der Massenimport:',
    'bulk_info_text'     => 'Füge eine Liste von Character-Namen ein (einer pro Zeile). Wähle ein Datum. Es wird automatisch eine Operation namens "Allianz FAT <Monat> <Jahr>" erstellt und mit dem Tag "Allypap" versehen. PAPs werden immer dem Haupt-Character des jeweiligen Users gutgeschrieben.',
    'bulk_date'          => 'Datum',
    'bulk_date_hint'     => 'Wähle das Datum der Flotte. Der Operationsname wird daraus automatisch generiert.',
    'bulk_auto_name'     => 'Operationsname (automatisch)',
    'bulk_auto_name_hint'=> 'Wird automatisch als "Allianz FAT <Monat> <Jahr>" aus dem gewählten Datum generiert.',
    'bulk_auto_tag'      => 'Tag (fest)',
    'bulk_list_label'    => 'Character-Liste',
    'bulk_list_placeholder' => "Character Name 1\nCharacter Name 2\nCharacter Name 3\n...",
    'bulk_submit'        => 'PAPs importieren',
    'back_to_single'     => 'Zurück zur einzelnen PAP',

    // Report
    'report_month'       => 'Monat',
    'report_year'        => 'Jahr',
    'report_filter'      => 'Filtern',
    'report_character'   => 'Haupt-Character',
    'report_char_id'     => 'Character ID',
    'report_total_paps'  => 'Gesamt FATs',
    'report_total'       => 'Gesamt',
    'report_unique_chars'=> 'Einzigartige Characters',
    'report_no_data'     => 'Keine PAPs für diesen Monat/Jahr gefunden.',
    'report_api_hint'    => 'Auch als API Endpunkt verfügbar',
];
