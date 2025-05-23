<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'ERSENA') }}</title>
        <link rel="stylesheet" href="{{ asset('css/common.css') }}">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="{{ asset('css/welcome.css') }}">
    </head>
    <body>
        <div class="top-bar">
            <div class="logo">
                <img src="{{ asset('img/logo/logo.png') }}" alt="ERSENA Logo">
            </div>
            <div id="anuncio-container">
                <div class="anuncio visible" data-type="bienvenida">

                </div>
            </div>
            <a href="{{ route('login') }}">
                <button class="btn-login">Iniciar Sesión</button>
            </a>
        </div>

        <div class="main-content">
            <div class="container">
                <div class="header">
                    <h1>SENA Regional Caquetá</h1>
                    <h2>Control de entradas de aprendices</h2>
                    <div class="update-time-container">
                        <i class="fas fa-clock"></i>
                        <span id="update-time"></span>
                    </div>
                </div>

                <div class="sidebar">
                    <div class="counter-box">
                        <div class="counter-label">Total Asistencias</div>
                        <div class="counter-value" id="total-count">0</div>
                    </div>

                    <div class="ranking-box">
                        <div class="ranking-title">Top 5 - Puntualidad</div>
                        <ul class="ranking-list" id="ranking-list">
                            <!-- El ranking se cargará dinámicamente -->
                        </ul>
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Aprendiz</th>
                                <th>Programa</th>
                                <th>Jornada</th>
                                <th>Registro</th>
                            </tr>
                        </thead>
                        <tbody id="asistencias-body">
                            <!-- Los datos serán cargados dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
            let asistenciasInterval;

            // Inicializar cuando se carga el documento
            document.addEventListener('DOMContentLoaded', function() {
                // Cargar asistencias inmediatamente
                loadAsistencias();
                
                // Configurar actualización automática cada 1 segundo
                asistenciasInterval = setInterval(loadAsistencias, 1000);
            });

            function loadAsistencias() {
                fetch('/api/asistencias/diarias')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error en la respuesta del servidor: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Datos recibidos:', data); // Debug
                        if (data.status === 'success') {
                            updateTable(data.data);
                            updateCounter(data.data);
                            document.getElementById('update-time').textContent = 
                                new Date().toLocaleTimeString('es-CO', { 
                                    hour: '2-digit', 
                                    minute: '2-digit',
                                    second: '2-digit',
                                    hour12: true 
                                });
                        } else {
                            console.error('Error en los datos:', data);
                            throw new Error(data.message || 'Error al cargar las asistencias');
                        }
                    })
                    .catch(error => {
                        console.error('Error al cargar asistencias:', error);
                        document.getElementById('asistencias-body').innerHTML = `
                            <tr>
                                <td colspan="4" class="text-center py-4">
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        Error al cargar las asistencias: ${error.message}
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
            }

            function updateTable(asistencias) {
                const tableBody = document.getElementById('asistencias-body');
                
                if (!asistencias || asistencias.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center py-4">
                                <div class="empty-message">
                                    <i class="fas fa-info-circle"></i>
                                    No hay asistencias registradas para el día de hoy
                                </div>
                            </td>
                        </tr>
                    `;
                    return;
                }

                // Agrupar asistencias por usuario
                const asistenciasPorUsuario = {};
                asistencias.forEach(asistencia => {
                    if (!asistenciasPorUsuario[asistencia.user_id]) {
                        asistenciasPorUsuario[asistencia.user_id] = {
                            user: asistencia.user,
                            entrada: null,
                            salida: null
                        };
                    }
                    if (asistencia.tipo === 'entrada') {
                        asistenciasPorUsuario[asistencia.user_id].entrada = asistencia;
                    } else if (asistencia.tipo === 'salida') {
                        asistenciasPorUsuario[asistencia.user_id].salida = asistencia;
                    }
                });

                // Convertir a array y ordenar por hora de entrada más reciente
                const registrosOrdenados = Object.values(asistenciasPorUsuario)
                    .sort((a, b) => {
                        const fechaA = a.entrada ? new Date(a.entrada.fecha_hora) : new Date(0);
                        const fechaB = b.entrada ? new Date(b.entrada.fecha_hora) : new Date(0);
                        return fechaB - fechaA;
                    });

                tableBody.innerHTML = '';
                registrosOrdenados.forEach(registro => {
                    const user = registro.user;
                    if (!user) return;

                    const horaEntrada = registro.entrada ? formatTime(registro.entrada.fecha_hora) : '---';
                    const horaSalida = registro.salida ? formatTime(registro.salida.fecha_hora) : '---';
                    const row = document.createElement('tr');
                    
                    row.innerHTML = `
                            <td>
                                <div class="user-info">
                                    <div class="user-name">${user.nombres_completos || 'N/A'}</div>
                                    <div class="user-details">
                                        <div class="user-doc">Doc: ${user.documento_identidad || 'N/A'}</div>
                                        ${user.devices && user.devices.length > 0 ? `
                                            <div class="device-info">
                                                <i class="fas fa-laptop"></i>
                                                ${user.devices[0].marca} - ${user.devices[0].serial}
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="program-info">
                                    <div class="program-name">${user.programa_formacion?.nombre_programa || 'N/A'}</div>
                                    <div class="program-details">
                                        <div class="program-nivel">
                                            <i class="fas fa-graduation-cap"></i>
                                            ${user.programa_formacion?.nivel_formacion?.toUpperCase() || 'N/A'}
                                        </div>
                                        <div>Ficha: ${user.programa_formacion?.numero_ficha || 'N/A'}</div>
                                        <div>Ambiente: ${user.programa_formacion?.numero_ambiente || 'N/A'}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="jornada-info">
                                    <span class="badge badge-jornada">
                                        <i class="fas fa-clock"></i>
                                        ${user.jornada?.nombre?.toUpperCase() || 'N/A'}
                                    </span>
                                    <div class="jornada-details">
                                        <div>Entrada: ${user.jornada?.hora_entrada || 'N/A'}</div>
                                        <div>Tolerancia: ${user.jornada?.tolerancia || '5 min'}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="time-info">
                                    <div class="registro-tiempo ${registro.entrada ? 'presente' : ''}">
                                        <span class="badge badge-entrada">
                                            <i class="fas fa-sign-in-alt"></i>
                                            ${horaEntrada}
                                        </span>
                                    </div>
                                    <div class="registro-tiempo ${registro.salida ? 'presente' : ''}">
                                        <span class="badge badge-salida">
                                            <i class="fas fa-sign-out-alt"></i>
                                            ${horaSalida}
                                        </span>
                                    </div>
                                </div>
                            </td>`;

                    // Efecto de nueva entrada
                    if (registro.entrada && isRecent(registro.entrada.fecha_hora)) {
                        row.classList.add('new-entry');
                        setTimeout(() => row.classList.remove('new-entry'), 5000);
                    }

                    tableBody.appendChild(row);
                });
            }
            
            function formatTime(dateString) {
                return new Date(dateString).toLocaleTimeString('es-CO', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });
            }

            function isRecent(dateString) {
                const entryTime = new Date(dateString);
                const now = new Date();
                return (now - entryTime) < 60000; // 1 minuto
            }

            function updateCounter(asistencias) {
                if (!asistencias) return;
                
                const usuariosUnicos = new Set(asistencias.map(a => a.user_id)).size;
                const counterElement = document.getElementById('total-count');
                
                if (counterElement) {
                    const currentValue = parseInt(counterElement.textContent) || 0;
                    if (currentValue !== usuariosUnicos) {
                        animateCounter(currentValue, usuariosUnicos, counterElement);
                    }
                }
            }

            function animateCounter(start, end, element) {
                const duration = 1000;
                const steps = 20;
                const increment = (end - start) / steps;
                let current = start;
                const stepTime = duration / steps;

                const timer = setInterval(() => {
                    current += increment;
                    if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                        clearInterval(timer);
                        element.textContent = end;
                    } else {
                        element.textContent = Math.round(current);
                    }
                }, stepTime);
            }
        </script>

        <style>
            /* Estilos adicionales para las animaciones y mejoras visuales */
            .asistencia-row {
                transition: background-color 0.3s ease;
            }

            .new-entry {
                animation: highlightNew 5s ease;
            }

            @keyframes highlightNew {
                0% { background-color: rgba(57, 169, 0, 0.2); }
                100% { background-color: transparent; }
            }

            .error-message, .empty-message {
                text-align: center;
                padding: 20px;
                color: #666;
            }

            .error-message i, .empty-message i {
                margin-right: 8px;
                color: #ff4444;
            }

            .empty-message i {
                color: #999;
            }

            .badge {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 5px 10px;
                border-radius: 15px;
                font-size: 0.9em;
                transition: opacity 0.3s ease;
            }

            .badge-entrada {
                background-color: rgba(57, 169, 0, 0.1);
                color: #39A900;
            }

            .badge-salida {
                background-color: rgba(75, 85, 99, 0.1);
                color: #4b5563;
            }

            .badge-jornada {
                background-color: rgba(59, 130, 246, 0.1);
                color: #3b82f6;
            }

            .jornada-info {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .jornada-details {
                font-size: 0.85rem;
                color: #64748b;
            }

            .program-details {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }

            .program-details div {
                color: #64748b;
                font-size: 0.85rem;
            }

            .user-details {
                display: flex;
                flex-direction: column;
                gap: 4px;
                margin-top: 4px;
            }

            .device-info {
                display: flex;
                align-items: center;
                gap: 6px;
                color: #64748b;
                font-size: 0.85rem;
            }

            .device-info i {
                color: #3b82f6;
            }

            .user-doc {
                color: #64748b;
                font-size: 0.85rem;
            }

            .user-name {
                font-weight: 500;
                color: #334155;
            }

            /* Ajuste responsive */
            @media (max-width: 768px) {
                .device-info {
                    font-size: 0.8rem;
                }
            }

            .registro-tiempo:not(.presente) .badge {
                opacity: 0.5;
            }
        </style>
    </body>
</html>