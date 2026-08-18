<?php

namespace Database\Seeders;

use App\Enums\Roles;
use App\Models\Caso;
use App\Models\EstadoExamenEstudiante;
use App\Models\EstadoExamenEvaluador;
use App\Models\Examen;
use App\Models\ExamenEstudiantePseudonimo;
use App\Models\Item;
use App\Models\NotaDefensa;
use App\Models\NotaItem;
use App\Models\Pregunta;
use App\Models\Pseudonimo;
use App\Models\RespuestaEstudiante;
use App\Models\Seccion;
use App\Models\TablaCotejo;
use App\Models\User;
use App\Services\PuntajeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LegacySeeder extends Seeder
{
    /** Cantidad total de estudiantes a sembrar para simulación */
    private const ESTUDIANTES = 10;

    /** estudiantes que deben quedar con *todas* las preguntas/ítems corregidos */
    private const COMPLETOS = 8;

    /** estudiantes que deben quedar con *algunas* preguntas completamente corregidas */
    private const PARCIALES = 0;

    /** Probabilidad de que un estudiante tenga defensa cargada si ya tiene correcciones */
    private const DEFENSA_RATIO = 0.40;

    /** Mínimo de ítems aprobados (0‑1) al azar */
    private const RESP_APROBACION_MIN = 0.0;

    public function run(): void
    {
        $puntajeService = app(PuntajeService::class);

        DB::transaction(function () use ($puntajeService) {
            // 1) Usuarios legacy
            [$administrador, $evaluador, $estudiante] = $this->seedUsuariosLegacy();

            // 2) Examen + estructura base
            [$examen, $seccion, $caso, $tabla] = $this->seedEstructuraLegacy($administrador, $evaluador, $estudiante);

            // 3) Preguntas + ítems (incluye Pregunta 7 informativa)
            $this->seedPreguntasEItems($examen, $caso, $seccion, $tabla);

            // 4) Simulación de respuestas, correcciones y estados
            $this->seedResultadosSimulados($puntajeService, $examen, $caso, $seccion);
        });
    }

    /**
     * Crea/actualiza los 3 usuarios legacy con roles correspondientes.
     * Retorna [admin, evaluador, estudiante]
     */
    private function seedUsuariosLegacy(): array
    {
        /*────────────────────────────────────────────────────────────
         * 1. Administrador y Evaluador
         *───────────────────────────────────────────────────────────*/
        $administrador = User::firstOrCreate(
            ['dni' => '00000001'],
            [
                'usuario' => 'administrador-legacy',
                'password' => Hash::make($this->demoPassword()),
                'nombre' => 'Administrador',
                'apellido' => 'Legacy',
                'legajo' => '000001',
                'email' => 'adminlegacy@example.com',
            ]
        );
        $administrador->syncRoles([Roles::ADMINISTRADOR->value]);

        $evaluador = User::firstOrCreate(
            ['dni' => '00000002'],
            [
                'usuario' => 'evaluador-legacy',
                'password' => Hash::make($this->demoPassword()),
                'nombre' => 'Evaluador',
                'apellido' => 'Legacy',
                'legajo' => '000002',
                'email' => 'evaluadorlegacy@example.com',
            ]
        );
        $evaluador->assignRole(Roles::EVALUADOR->value);

        $estudiante = User::firstOrCreate(
            ['dni' => '00000003'],
            [
                'usuario' => 'estudiante-legacy',
                'password' => Hash::make($this->demoPassword()),
                'nombre' => 'Estudiante',
                'apellido' => 'Legacy',
                'legajo' => '000003',
                'email' => 'estudiantelegacy@example.com',
            ]
        );
        $estudiante->assignRole(Roles::ESTUDIANTE->value);

        return [$administrador, $evaluador, $estudiante];
    }

    /**
     * Crea/actualiza examen, seccion, caso y tabla de cotejo.
     * Linkea usuarios y estados base. Asigna pseudónimo al estudiante legacy.
     * Retorna [examen, seccion, caso, tabla]
     */
    private function seedEstructuraLegacy($administrador, $evaluador, $estudiante): array
    {
        /*────────────────────────────────────────────────────────────
         * 2. Examen
         *───────────────────────────────────────────────────────────*/
        $examen = Examen::firstOrCreate(
            ['codigo' => 'REMEDIACION'],
            [
                'nombre' => 'RemediaciónExGlobal',
                'fecha' => '2024-02-19',
                'publicado' => false,
                'finalizado' => false,
                'defendido' => false,
                'descargable' => true,
                'creado_por' => $administrador->id,
            ]
        );

        $administrador->syncRolesPorExamen($examen->id, [Roles::ADMINISTRADOR->value]);
        $evaluador->syncRolesPorExamen($examen->id, [Roles::EVALUADOR->value]);
        $estudiante->syncRolesPorExamen($examen->id, [Roles::ESTUDIANTE->value]);

        EstadoExamenEvaluador::updateOrCreate(
            [
                'evaluador_id' => $evaluador->id,
                'examen_id' => $examen->id,
            ],
            [
                'finalizado' => false,
                'correcciones_asignadas' => 0,
                'correcciones_realizadas' => 0,
            ]
        );

        EstadoExamenEstudiante::updateOrCreate(
            [
                'estudiante_id' => $estudiante->id,
                'examen_id' => $examen->id,
            ]
        );

        /*────────────────────────────────────────────────────────────
         * 2.1 Pseudónimo del estudiante + link a examen (habilita rendir)
         *───────────────────────────────────────────────────────────*/
        $pseudonimo = Pseudonimo::firstOrCreate(
            ['nombre' => '1001'],
        );

        // Evitar duplicar pivot (unique examen_id + estudiante_id)
        ExamenEstudiantePseudonimo::updateOrCreate(
            [
                'examen_id' => $examen->id,
                'estudiante_id' => $estudiante->id,
            ],
            [
                'pseudonimo_id' => $pseudonimo->id,
            ]
        );

        /*────────────────────────────────────────────────────────────
         * 3. Sección
         *───────────────────────────────────────────────────────────*/
        $seccion = Seccion::firstOrCreate([
            'examen_id' => $examen->id,
            'nombre' => 'REMEDIACION',
            'orden_relativo' => 1,
        ]);

        /*────────────────────────────────────────────────────────────
         * 4. Caso único
         *───────────────────────────────────────────────────────────*/
        $caso = Caso::firstOrCreate(
            ['examen_id' => $examen->id, 'titulo' => 'Unico'],
            [
                'evaluador_id' => $evaluador->id,
                'orden_relativo' => 1,
            ]
        );

        /*────────────────────────────────────────────────────────────
         * 5. Tabla de cotejo
         *───────────────────────────────────────────────────────────*/
        $tabla = TablaCotejo::firstOrCreate([
            'examen_id' => $examen->id,
            'caso_id' => $caso->id,
            'seccion_id' => $seccion->id,
        ]);

        return [$examen, $seccion, $caso, $tabla];
    }

    /**
     * Inserta/actualiza TODAS las preguntas + ítems del legacy (sin perder nada).
     * También agrega la Pregunta 7 informativa (sin consigna).
     */
    private function seedPreguntasEItems($examen, $caso, $seccion, $tabla): void
    {
        $legacyQuestions = [
            [
                'consigna' => 'Detalle cuál sería interrogatorio.',
                'descripcion' => <<<'TXT'
Ud es el médica de la Unidad Sanitaria de San Dionisio.
Es lunes y recibe por primera vez a Carlos, de 47 años, quien consulta para realizarse un control de salud.
No ve a un médico desde los 22 años.
TXT,
                'items' => [
                    ['Interroga vivienda', 1],
                    ['Interroga estructura familiar', 2],
                    ['Interroga dimensión económica', 3],
                    ['Interroga antecedentes patológicos', 4],
                    ['Interroga antecedentes familiares', 5],
                    ['Interroga alcohol', 6],
                    ['Interroga Tabaquismo', 7],
                    ['Interroga actividad física', 8],
                    ['Interroga vacunación', 9],
                    ['Interroga ánimo', 10],
                ],
            ],

            [
                'consigna' => 'Detalle cuál sería su examen físico, teniendo en cuenta que lo realiza en el marco de un control de salud.',
                'descripcion' => <<<'TXT'
Carlos vive a 12 cuadras de la Unidad Sanitaria; su casa es de material y cuenta con todos los servicios.
Utilizan calefacción eléctrica. Vive con su esposa Sandra (46) y sus hijos Carla (23), Mauro (21), Laura (19) y Milton (14).
Los ingresos económicos provienen del pequeño taller chapista de Carlos (trabaja de 8 a 18 hs, monotributista) y de Sandra, que hace limpieza doméstica de 8 a 12 hs.

Antecedentes: nunca tuvo enfermedades relevantes. Padre, tío y un hermano con cáncer de colon (el hermano a los 49 años).
Consume un vaso de vino diario y “castiga” los sábados con 3-4 l de cerveza (“puede manejar sin problemas”, incluso con 1.8 g/L de alcohol en sangre).
Fuma desde los 15 años (15 cig/día) y no piensa dejarlo en los próximos 6 meses.

Alimentación variada (frutas, vegetales, carnes) y cuatro comidas; no picotea. Es sedentario y “sin tiempo” para actividad física.
Última vacunación: servicio militar (1985).
TXT,
                'items' => [
                    ['Pesa', 0.50],
                    ['Toma talla', 1.50],
                    ['Toma TA', 2.50],
                    ['Realiza agudeza visual', 3.50],
                    ['Evalúa fauces', 4.50],
                    ['No realiza examen innecesario (auscultación respiratoria, palpación abdominal, tacto rectal)', 5.50],
                ],
            ],

            [
                'consigna' => 'Detalle los problemas de salud detectados en esta entrevista (incluyendo factores de riesgo)…',
                'descripcion' => <<<'TXT'
Examen físico

• Peso 82 kg - Talla 1,75 m - Cintura 117 cm
• Índice de Masa Corporal ≈ 26.8 ( sobrepeso )
• PA 150/85 mmHg
• Agudeza visual 9/10 ambos ojos
• Dentadura en buen estado
TXT,
                'items' => [
                    ['Identifica sobrepeso', 6.50],
                    ['Identifica el consumo de alcohol como un problema', 7.50],
                    ['Identifica tabaquismo', 8.50],
                    ['Dice que tiene tensión arterial elevada, pero no dice HTA', 9.50],
                ],
            ],

            [
                'consigna' => 'Qué estudios complementarios considera que deberían agregarse en este momento',
                'descripcion' => <<<'TXT'
— Identifica sobrepeso (IMC)
— Un registro de presión elevado, pero sin diagnóstico de HTA
— Tabaquista en fase de pre-contemplación
— Abuso de alcohol

Carlos vuelve 2 semanas después con:

• Glucemia en ayunas 129 mg/dL
• Colesterol total 199 mg/dL - HDL 67 mg/dL - TG 189 mg/dL
• Registros de PA (enfermería): 80 % > 150/80 mmHg
TXT,
                'items' => [
                    ['Solicita un ECG', 0.50],
                    ['Solicita nueva glucemia basal', 2.50],
                ],
            ],

            [
                'consigna' => 'Describa de forma sistemática este ECG… y su opinión sobre la glucemia basal',
                'descripcion' => <<<'TXT'
Ya con diagnóstico de HTA solicita un ECG; la segunda glucemia en ayunas es 135 mg/dL.
TXT,
                'items' => [
                    ['ECG: analiza FC ≈ 100/min', 1],
                    ['ECG: analiza ritmo regular', 2],
                    ['ECG: analiza ritmo sinusal', 3],
                    ['ECG: analiza eje entre 0 y 90°', 4],
                    ['ECG: analiza ST normal', 5],
                    ['ECG: analiza QRS normal', 6],
                    ['Diagnostica Diabetes Mellitus tipo 2 (especifica todo)', 7],
                    ['Recomienda reducir sodio', 8],
                    ['Realiza recomendación alimentaria pertinente', 9],
                    ['Recomienda realizar actividad física', 10],
                    ['Menciona inmunizaciones (al menos COVID y gripe)', 1],
                ],
            ],

            [
                'consigna' => 'En función de la evidencia presentada… ¿Recomendaría Ud. Metformina?',
                'descripcion' => <<<'TXT'
Tras 4 meses Carlos mejoró alimentación y hace ejercicio aeróbico 40' x 5 días/sem.
La glucemia basal de control es 130 mg/dL.
Se plantea iniciar Metformina basándose en la evidencia adjunta.
TXT,
                'items' => [
                    ['Identifica que la recomendación proviene de un panel (método GRADE)', 0.50],
                    ['Identifica la tabla SoF (Resumen de la evidencia)', 1.50],
                    ['Menciona que es un estudio en la cima de la pirámide de evidencia', 2.50],
                    ['Explica que es un estudio terciario (RS + recomendaciones basadas en evidencia)', 3.50],
                    ['Entiende que las mejoras de hábitos no impactaron significativamente en la glucemia', 4.50],
                    ['La recomendación es condicional: decisiones individualizadas con Carlos', 5.50],
                ],
            ],
        ];
        // Agregamos Pregunta 7 informativa (sin consigna), como pediste
        $legacyQuestions[] = [
            'consigna' => null,
            'descripcion' => 'Pregunta informativa en la descripción',
            'items' => [],
        ];

        // Inserción segura (idempotente) de preguntas e ítems
        foreach ($legacyQuestions as $index => $q) {
            $orden = $index + 1;

            $pregunta = Pregunta::updateOrCreate(
                [
                    'examen_id' => $examen->id,
                    'caso_id' => $caso->id,
                    'orden_relativo' => $orden,
                ],
                [
                    'consigna' => $q['consigna'] ?? null,
                    'descripcion' => $q['descripcion'] ?? '',
                ]
            );

            foreach ($q['items'] as [$descripcion, $valor]) {
                Item::updateOrCreate(
                    [
                        'examen_id' => $examen->id,
                        'caso_id' => $caso->id,
                        'seccion_id' => $seccion->id,
                        'tabla_cotejo_id' => $tabla->id,
                        'pregunta_id' => $pregunta->id,
                        'descripcion' => $descripcion,
                    ],
                    [
                        'valor' => $valor,
                    ]
                );
            }
        }
    }

    /**
     * Simula respuestas, correcciones, defensas y estados para N estudiantes,
     * exactamente como en el legacy original (manteniendo probabilidades y reglas).
     */
    private function seedResultadosSimulados($puntajeService, $examen, $caso, $seccion): void
    {
        // Estructura
        $preguntas = $caso->preguntas;
        $items = $caso->preguntas->flatMap->items;

        /* 2. Estudiantes + pseudónimos (10 en total) */
        $estudiantes = collect(range(1, self::ESTUDIANTES))->map(function (int $i) {
            $user = User::factory()->create([
                'dni' => fake()->unique()->numberBetween(00000003, 99999999),
                'legajo' => fake()->unique()->numberBetween(000003, 999999),
                'usuario' => Str::slug(fake()->unique()->userName()),
            ])->assignRole('estudiante');

            $pseudonimo = Pseudonimo::firstOrCreate(['nombre' => (string) $i]);

            return compact('user', 'pseudonimo');
        });

        /* 3. Pivot examen_user_roles + pseudonimo */
        $estudiantes->each(function (array $pair) use ($examen) {
            $pair['user']->syncRolesPorExamen($examen->id, [Roles::ESTUDIANTE->value]);

            ExamenEstudiantePseudonimo::updateOrCreate(
                [
                    'examen_id' => $examen->id,
                    'estudiante_id' => $pair['user']->id,
                ],
                [
                    'pseudonimo_id' => $pair['pseudonimo']->id,
                ]
            );
        });

        /* 4. Respuestas, correcciones y estados */
        $estudiantes->each(function (array $pair, int $idx) use ($caso, $preguntas, $items, $examen, $seccion, $puntajeService) {
            $user = $pair['user'];

            /* 4.1 Respuestas (todos los estudiantes entregan algo) */
            foreach ($preguntas as $pregunta) {
                if ($pregunta->consigna === null) {
                    continue;
                }

                RespuestaEstudiante::factory()->create([
                    'respuesta' => fake()->paragraphs(rand(1, 3), true),
                    'estudiante_id' => $user->id,
                    'pregunta_id' => $pregunta->id,
                ]);
            }

            /* Categoría del estudiante según índice */
            $esCompleto = $idx < self::COMPLETOS;
            $esParcial = ! $esCompleto && $idx < (self::COMPLETOS + self::PARCIALES);
            $esSinNotas = ! $esCompleto && ! $esParcial;

            /* 4.2 Correcciones */
            $tieneDefensa = false;

            if ($esSinNotas) {
                // No se crean notas para este estudiante
            } else {
                if ($esCompleto) {
                    // *** Caso COMPLETO: todos los ítems reciben nota ***
                    $take = round($items->count() * fake()->randomFloat(2, self::RESP_APROBACION_MIN, 1));
                    $aprobados = $take ? $items->pluck('id')->random($take)->flip() : collect();

                    foreach ($items as $item) {
                        NotaItem::factory()->create([
                            'item_id' => $item->id,
                            'estudiante_id' => $user->id,
                            'aprobado' => $aprobados->has($item->id),
                        ]);
                    }
                } else {
                    // *** Caso PARCIAL: elegimos aleatoriamente algunas preguntas ***
                    $preguntasSeleccionadas = $preguntas->random(fake()->numberBetween(1, $preguntas->count() - 1));

                    // Para cada pregunta elegida: todos sus ítems reciben nota
                    $preguntasSeleccionadas->each(function ($pregunta) use ($user) {
                        $itemsPregunta = $pregunta->items;
                        $take = round($itemsPregunta->count() * fake()->randomFloat(2, self::RESP_APROBACION_MIN, 1));
                        $aprobados = $take ? $itemsPregunta->pluck('id')->random($take)->flip() : collect();

                        foreach ($itemsPregunta as $item) {
                            NotaItem::factory()->create([
                                'item_id' => $item->id,
                                'estudiante_id' => $user->id,
                                'aprobado' => $aprobados->has($item->id),
                            ]);
                        }
                    });
                }

                /* 4.2.1 Defensa (opcional) */
                $tieneDefensa = fake()->boolean(self::DEFENSA_RATIO * 100);

                if ($tieneDefensa) {
                    // Máximo de la sección del caso (solo ítems)
                    $maxSeccion = $puntajeService->maxCasoSeccion($caso, $seccion);
                    // Puntaje obtenido por el estudiante en esa sección del caso (sin defensas)
                    $obtenidoSeccion = $puntajeService->puntajeObtenidoCasoSeccion($caso, $seccion, $user);
                    // Defensa disponible = lo que falta para llegar al máximo de la sección (no negativo)
                    $maxDefensa = max(0.0, $maxSeccion - $obtenidoSeccion);

                    if ($maxDefensa > 0) {
                        NotaDefensa::factory()->create([
                            'estudiante_id' => $user->id,
                            'caso_id' => $caso->id,
                            'seccion_id' => $seccion->id,
                            'nota' => fake()->randomFloat(2, 0, $maxDefensa),
                        ]);
                    }
                }
            }

            /* 4.3 Estado del estudiante */
            if ($esCompleto) {
                $ultimaPregunta = $preguntas->sortBy('orden_relativo')->last();
                $casoId = $caso->id;
                $preguntaId = $ultimaPregunta->id;
            } elseif ($esParcial) {
                $preguntaRandom = $preguntas->random();
                $casoId = $caso->id;
                $preguntaId = $preguntaRandom->id;
            } else {
                $primeraPregunta = $preguntas->sortBy('orden_relativo')->first();
                $casoId = $caso->id;
                $preguntaId = $primeraPregunta->id;
            }

            EstadoExamenEstudiante::factory()->create([
                'inicio' => true,
                'finalizado' => $esCompleto ? true : false,
                'defendido' => $esCompleto && $tieneDefensa,
                'hora_inicio' => now()->subMinutes(fake()->numberBetween(70, 90)),
                'hora_finalizacion' => $esCompleto ? now()->subMinutes(fake()->numberBetween(20, 40)) : null,
                'alertado' => false,
                'posicion_finalizacion' => $esCompleto ? ($idx + 1) : null,
                'caso_id' => $casoId,
                'pregunta_id' => $preguntaId,
                'estudiante_id' => $user->id,
                'examen_id' => $examen->id,
            ]);
        });
    }

    private function demoPassword(): string
    {
        $password = trim((string) config('filomena.demo_user_password'));

        if ($password === '' || $password === 'change-me') {
            throw new \RuntimeException(
                'Configure DEMO_USER_PASSWORD before running non-production seeders.'
            );
        }

        return $password;
    }
}
