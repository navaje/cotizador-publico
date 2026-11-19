<?php
function ojc_schema(): array {
    return [
        'camera'=>[
            'imagen'=>'Imagen nítida y enfoque en la zona de interés',
            'lente'=>'Lente, cristal o domo limpio y sin condensación',
            'cobertura'=>'Orientación, cobertura y ausencia de obstrucciones',
            'fijacion'=>'Soporte firme, tornillería y montaje',
            'carcasa'=>'Carcasa sin daños, humedad ni entrada de agua',
            'noche'=>'Visión nocturna e infrarrojo sin reflejos perjudiciales',
            'ptz'=>'Zoom, paneo, inclinación y posiciones PTZ (si aplica)',
            'movimiento'=>'Prueba de detección de movimiento y zonas de eventos',
            'audio'=>'Captura y reproducción de audio (si aplica)',
            'grabacion'=>'El canal graba y reproduce una prueba reciente'
        ],
        'groups'=>[
            'cableado'=>['label'=>'Cableado y alimentación','checks'=>[
                'cables'=>'Cables, conectores, empalmes y canalizaciones',
                'conexion'=>'Sin conexiones flojas, corrosión ni conductores expuestos',
                'fuentes'=>'Fuentes y alimentación adecuadas al equipo',
                'respaldo'=>'Funcionamiento del respaldo eléctrico (si existe)']],
            'red'=>['label'=>'Red y alimentación PoE - IP / híbrido','checks'=>[
                'rj45'=>'Conectores RJ45, enlaces y alimentación PoE',
                'ip'=>'Direcciones IP sin conflictos',
                'switch'=>'Capacidad del switch y presupuesto PoE',
                'estabilidad'=>'Conexión estable, sin desconexiones observadas']],
            'grabador'=>['label'=>'DVR/NVR y almacenamiento','checks'=>[
                'ventilacion'=>'Encendido, ventilación, limpieza y temperatura de operación',
                'canales'=>'Todos los canales esperados conectados',
                'disco'=>'Estado del almacenamiento y alertas del grabador',
                'horario'=>'Horarios y modalidad de grabación configurados',
                'reproduccion'=>'Reproducción comprobada en distintos canales',
                'exportacion'=>'Exportación de prueba y apertura del archivo',
                'retencion'=>'Días de grabación disponibles frente a los requeridos',
                'lleno'=>'Comportamiento configurado al llenarse el almacenamiento']],
            'configuracion'=>['label'=>'Visualización y configuración','checks'=>[
                'video'=>'Calidad de video en vivo y grabado',
                'monitor'=>'Claridad del monitor, brillo y contraste',
                'hora'=>'Fecha, hora, zona horaria y sincronización',
                'acceso'=>'Acceso local y remoto desde dispositivos autorizados',
                'alertas'=>'Prueba de alertas y notificaciones habilitadas',
                'cuentas'=>'Cuentas y permisos revisados sin registrar contraseñas']]
        ]
    ];
}
function ojc_statuses(): array {
    return ['pendiente'=>'Pendiente','cumple'=>'Cumple','atencion'=>'Requiere atención','no_cumple'=>'No cumple','na'=>'No aplica'];
}
