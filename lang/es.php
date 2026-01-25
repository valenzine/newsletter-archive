<?php

/**
 * Spanish (Argentina) Language File
 * 
 * Archivo de traducción al español para Newsletter Archive.
 */

return [
    // Site
    'site' => [
        'title' => 'Archivo de Newsletter',
        'description' => 'Archivo de campañas de email anteriores',
    ],
    
    // Navigation
    'nav' => [
        'back_to_list' => 'Volver a la lista',
        'back_to_search' => 'Volver a la búsqueda',
        'previous' => 'Anterior',
        'next' => 'Siguiente',
        'search' => 'Buscar',
        'admin' => 'Administración',
    ],
    
    // Sorting
    'sort' => [
        'newest_first' => 'Ordenar: Más recientes primero',
        'oldest_first' => 'Ordenar: Más antiguos primero',
    ],
    
    // Campaign list
    'list' => [
        'loading' => 'Cargando campañas...',
        'no_campaigns' => 'No se encontraron campañas',
        'select_campaign' => 'Seleccioná un email de la lista para leerlo',
    ],
    
    // Campaign view
    'campaign' => [
        'loading' => 'Cargando campaña...',
        'not_found' => 'Campaña no encontrada',
        'load_error' => 'Error al cargar el contenido de la campaña',
    ],
    
    // Search
    'search' => [
        'page_title' => 'Buscar en el archivo',
        'title' => 'Buscar en el archivo',
        'subtitle' => 'Buscá entre %s campañas',
        'placeholder' => '¿Qué estás buscando?',
        'button' => 'Buscar',
        'clear' => 'Limpiar búsqueda',
        'no_results' => 'No se encontraron resultados para "{query}"',
        'results_count' => '{count} resultados encontrados',
        'searching' => 'Buscando...',
        'enter_query' => 'Ingresá un término de búsqueda para comenzar',
        'filters' => 'Filtros',
        'date_range' => 'Rango de fechas',
        'date_from' => 'Desde',
        'date_to' => 'Hasta',
        'sort_by' => 'Ordenar por',
        'sort_relevance' => 'Relevancia',
        'sort_newest' => 'Más recientes primero',
        'sort_oldest' => 'Más antiguos primero',
        'per_page' => 'Resultados por página',
        'clear_filters' => 'Limpiar filtros',
    ],
    
    // Admin
    'admin' => [
        'title' => 'Panel de Administración',
        'sync' => 'Sincronizar Campañas',
        'import' => 'Importar Campañas',
        'settings' => 'Configuración',
        'last_sync' => 'Última sincronización: {date}',
        'never_synced' => 'Nunca sincronizado',
        'sync_now' => 'Sincronizar Ahora',
        'syncing' => 'Sincronizando...',
        'sync_success' => 'Sincronización completada exitosamente',
        'sync_error' => 'Error en la sincronización: {error}',
        'campaigns_total' => 'Total de campañas: {count}',
        'campaigns_hidden' => 'Campañas ocultas: {count}',
    ],
    
    // Import
    'import' => [
        'title' => 'Importar Campañas de Mailchimp',
        'description' => 'Importá campañas desde un archivo ZIP exportado de Mailchimp.',
        'select_file' => 'Seleccionar archivo ZIP',
        'select_csv' => 'Seleccionar campaigns.csv',
        'importing' => 'Importando...',
        'success' => 'Se importaron {count} campañas exitosamente',
        'error' => 'Error en la importación: {error}',
        'no_file' => 'Por favor seleccioná un archivo para importar',
    ],
    
    // Errors
    'error' => [
        'generic' => 'Ocurrió un error',
        'not_found' => 'Página no encontrada',
        'server_error' => 'Error interno del servidor',
        'api_error' => 'Error en la solicitud a la API',
    ],
    
    // Dates (for JavaScript)
    'date' => [
        'months' => ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                     'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
        'months_short' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
                          'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
    ],
];
