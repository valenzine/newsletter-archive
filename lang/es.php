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
        'description' => 'Archivo de correos anteriores',
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
    
    // 404 / Errors
    '404' => [
        'title' => 'Página No Encontrada',
        'heading' => '404',
        'message' => 'Página no encontrada',
        'back_home' => 'Volver al inicio',
    ],
    
    // Setup Prompt (First-time installation)
    'setup_prompt' => [
        'title' => '¡Bienvenido a tu Archivo de Newsletter!',
        'description' => 'Este archivo aún no está configurado. Configurémoslo en unos pocos pasos.',
        'start_setup' => 'Comenzar Configuración',
        'complete_setup' => 'Completar Configuración',
        'skip_for_now' => 'Omitir por Ahora',
        'what_youll_do' => 'Lo que harás:',
        'step_create_account' => 'Creá tu cuenta de administrador',
        'step_customize' => 'Personalizá el título y la marca del sitio',
        'step_add_api_key' => 'Agregá tu clave API de MailerLite al archivo .env',
        'step_import' => 'Importá tus primeras campañas',
        'time_estimate' => '¡Lleva menos de 5 minutos completarlo!',
    ],
    
    // Sorting
    'sort' => [
        'newest_first' => 'Ordenar: Más recientes primero',
        'oldest_first' => 'Ordenar: Más antiguos primero',
    ],
    
    // Campaign list
    'list' => [
        'loading' => 'Cargando correos...',
        'no_campaigns' => 'No se encontraron correos',
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
        'search_query_title' => 'Búsqueda',
        'title' => 'Buscar en el archivo',
        'subtitle' => 'Buscá entre %s correos',
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
        'sync' => 'Sincronizar correos',
        'import' => 'Importar correos',
        'settings' => 'Configuración',
        'last_sync' => 'Última sincronización: {date}',
        'never_synced' => 'Nunca sincronizado',
        'sync_now' => 'Sincronizar Ahora',
        'syncing' => 'Sincronizando...',
        'sync_success' => 'Sincronización completada exitosamente',
        'sync_error' => 'Error en la sincronización: {error}',
        'campaigns_total' => 'Total de correos: {count}',
        'campaigns_hidden' => 'Correos ocultos: {count}',
    ],
    
    // Import
    'import' => [
        'title' => 'Importar correos de Mailchimp',
        'description' => 'Importá correos desde un archivo ZIP exportado de Mailchimp.',
        'select_file' => 'Seleccionar archivo ZIP',
        'select_csv' => 'Seleccionar campaigns.csv',
        'importing' => 'Importando...',
        'success' => 'Se importaron {count} correos exitosamente',
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
