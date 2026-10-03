<?php
/**
 * Subject taxonomy seed.
 *
 * A three-level academic subject taxonomy for the multilingual paper-publishing
 * platform. Every node carries a globally unique kebab-case slug (used as the
 * /categories/{slug} identifier) and a locale -> name map; zh-CN and en are
 * present on every node, and ja / ko / fr / de are supplied for the root
 * disciplines and for second-level subjects where the standard academic term is
 * well established in that language.
 *
 * Consumed by bin/migrate.php, which inserts only missing slugs and re-parents
 * the legacy top-level philosophy entries (metaphysics, epistemology, ethics,
 * logic, aesthetics, political-philosophy, phenomenology, philosophy-of-mind,
 * philosophy-of-science, chinese-philosophy, history-of-philosophy,
 * philosophy-of-religion) under philosophy.
 */

return [
    [
        'slug'     => 'philosophy',
        'names'    => ['zh-CN' => '哲学', 'en' => 'Philosophy', 'ja' => '哲学', 'ko' => '철학', 'fr' => 'Philosophie', 'de' => 'Philosophie'],
        'children' => [
            [
                'slug'     => 'metaphysics',
                'names'    => ['zh-CN' => '形而上学', 'en' => 'Metaphysics', 'ja' => '形而上学', 'ko' => '형이상학', 'fr' => 'Métaphysique', 'de' => 'Metaphysik'],
                'children' => [
                    [
                        'slug'     => 'philosophical-cosmology',
                        'names'    => ['zh-CN' => '哲学宇宙论', 'en' => 'Philosophical cosmology'],
                    ],
                    [
                        'slug'     => 'philosophy-of-time',
                        'names'    => ['zh-CN' => '时间哲学', 'en' => 'Philosophy of time'],
                    ]
                ],
            ],
            [
                'slug'     => 'epistemology',
                'names'    => ['zh-CN' => '认识论', 'en' => 'Epistemology', 'ja' => '認識論', 'ko' => '인식론', 'fr' => 'Épistémologie', 'de' => 'Erkenntnistheorie'],
                'children' => [
                    [
                        'slug'     => 'epistemology-of-testimony',
                        'names'    => ['zh-CN' => '证言认识论', 'en' => 'Epistemology of testimony'],
                    ],
                    [
                        'slug'     => 'social-epistemology',
                        'names'    => ['zh-CN' => '社会认识论', 'en' => 'Social epistemology'],
                    ],
                    [
                        'slug'     => 'formal-epistemology',
                        'names'    => ['zh-CN' => '形式认识论', 'en' => 'Formal epistemology'],
                    ]
                ],
            ],
            [
                'slug'     => 'ethics',
                'names'    => ['zh-CN' => '伦理学', 'en' => 'Ethics', 'ja' => '倫理学', 'ko' => '윤리학', 'fr' => 'Éthique', 'de' => 'Ethik'],
                'children' => [
                    [
                        'slug'     => 'normative-ethics',
                        'names'    => ['zh-CN' => '规范伦理学', 'en' => 'Normative ethics'],
                    ],
                    [
                        'slug'     => 'metaethics',
                        'names'    => ['zh-CN' => '元伦理学', 'en' => 'Metaethics'],
                    ],
                    [
                        'slug'     => 'applied-ethics',
                        'names'    => ['zh-CN' => '应用伦理学', 'en' => 'Applied ethics'],
                    ],
                    [
                        'slug'     => 'bioethics',
                        'names'    => ['zh-CN' => '生命伦理学', 'en' => 'Bioethics'],
                    ],
                    [
                        'slug'     => 'business-ethics',
                        'names'    => ['zh-CN' => '商业伦理学', 'en' => 'Business ethics'],
                    ],
                    [
                        'slug'     => 'moral-psychology',
                        'names'    => ['zh-CN' => '道德心理学', 'en' => 'Moral psychology'],
                    ]
                ],
            ],
            [
                'slug'     => 'logic',
                'names'    => ['zh-CN' => '逻辑学', 'en' => 'Logic', 'ja' => '論理学', 'ko' => '논리학', 'fr' => 'Logique', 'de' => 'Logik'],
                'children' => [
                    [
                        'slug'     => 'propositional-logic',
                        'names'    => ['zh-CN' => '命题逻辑', 'en' => 'Propositional logic'],
                    ],
                    [
                        'slug'     => 'first-order-logic',
                        'names'    => ['zh-CN' => '一阶逻辑', 'en' => 'First-order logic'],
                    ],
                    [
                        'slug'     => 'modal-logic',
                        'names'    => ['zh-CN' => '模态逻辑', 'en' => 'Modal logic'],
                    ],
                    [
                        'slug'     => 'non-classical-logic',
                        'names'    => ['zh-CN' => '非经典逻辑', 'en' => 'Non-classical logic'],
                    ]
                ],
            ],
            [
                'slug'     => 'aesthetics',
                'names'    => ['zh-CN' => '美学', 'en' => 'Aesthetics', 'ja' => '美学', 'ko' => '미학', 'fr' => 'Esthétique', 'de' => 'Ästhetik'],
                'children' => [
                    [
                        'slug'     => 'philosophy-of-music',
                        'names'    => ['zh-CN' => '音乐哲学', 'en' => 'Philosophy of music'],
                    ],
                    [
                        'slug'     => 'art-and-aesthetic-theory',
                        'names'    => ['zh-CN' => '艺术与审美理论', 'en' => 'Art and aesthetic theory'],
                    ],
                    [
                        'slug'     => 'everyday-aesthetics',
                        'names'    => ['zh-CN' => '日常生活美学', 'en' => 'Everyday aesthetics'],
                    ]
                ],
            ],
            [
                'slug'     => 'political-philosophy',
                'names'    => ['zh-CN' => '政治哲学', 'en' => 'Political philosophy', 'ja' => '政治哲学', 'ko' => '정치철학', 'fr' => 'Philosophie politique', 'de' => 'Politische Philosophie'],
                'children' => [
                    [
                        'slug'     => 'theories-of-justice',
                        'names'    => ['zh-CN' => '正义理论', 'en' => 'Theories of justice'],
                    ],
                    [
                        'slug'     => 'rights-and-obligations',
                        'names'    => ['zh-CN' => '权利与义务', 'en' => 'Rights and obligations'],
                    ],
                    [
                        'slug'     => 'democratic-theory',
                        'names'    => ['zh-CN' => '民主理论', 'en' => 'Democratic theory'],
                    ]
                ],
            ],
            [
                'slug'     => 'phenomenology',
                'names'    => ['zh-CN' => '现象学', 'en' => 'Phenomenology', 'ja' => '現象学', 'ko' => '현상학', 'fr' => 'Phénoménologie', 'de' => 'Phänomenologie'],
                'children' => [
                    [
                        'slug'     => 'existentialism',
                        'names'    => ['zh-CN' => '存在主义', 'en' => 'Existentialism'],
                    ],
                    [
                        'slug'     => 'hermeneutics',
                        'names'    => ['zh-CN' => '诠释学', 'en' => 'Hermeneutics'],
                    ]
                ],
            ],
            [
                'slug'     => 'philosophy-of-mind',
                'names'    => ['zh-CN' => '心灵哲学', 'en' => 'Philosophy of mind', 'ja' => '心の哲学', 'ko' => '심리철학', 'fr' => 'Philosophie de l’esprit', 'de' => 'Philosophie des Geistes'],
                'children' => [
                    [
                        'slug'     => 'consciousness-studies',
                        'names'    => ['zh-CN' => '意识研究', 'en' => 'Consciousness studies'],
                    ],
                    [
                        'slug'     => 'personal-identity',
                        'names'    => ['zh-CN' => '人格同一性', 'en' => 'Personal identity'],
                    ],
                    [
                        'slug'     => 'mind-body-problem',
                        'names'    => ['zh-CN' => '心身问题', 'en' => 'Mind-body problem'],
                    ]
                ],
            ],
            [
                'slug'     => 'philosophy-of-science',
                'names'    => ['zh-CN' => '科学哲学', 'en' => 'Philosophy of science', 'ja' => '科学哲学', 'ko' => '과학철학', 'fr' => 'Philosophie des sciences', 'de' => 'Wissenschaftsphilosophie'],
                'children' => [
                    [
                        'slug'     => 'general-philosophy-of-science',
                        'names'    => ['zh-CN' => '一般科学哲学', 'en' => 'General philosophy of science'],
                    ],
                    [
                        'slug'     => 'scientific-explanation',
                        'names'    => ['zh-CN' => '科学解释', 'en' => 'Scientific explanation'],
                    ],
                    [
                        'slug'     => 'scientific-realism',
                        'names'    => ['zh-CN' => '科学实在论', 'en' => 'Scientific realism'],
                    ],
                    [
                        'slug'     => 'scientific-methodology',
                        'names'    => ['zh-CN' => '科学方法论', 'en' => 'Scientific methodology'],
                    ],
                    [
                        'slug'     => 'philosophy-of-physics',
                        'names'    => ['zh-CN' => '物理学哲学', 'en' => 'Philosophy of physics'],
                    ],
                    [
                        'slug'     => 'philosophy-of-biology',
                        'names'    => ['zh-CN' => '生物学哲学', 'en' => 'Philosophy of biology'],
                    ]
                ],
            ],
            [
                'slug'     => 'chinese-philosophy',
                'names'    => ['zh-CN' => '中国哲学', 'en' => 'Chinese philosophy', 'ja' => '中国哲学', 'ko' => '중국철학', 'fr' => 'Philosophie chinoise', 'de' => 'Chinesische Philosophie'],
                'children' => [
                    [
                        'slug'     => 'pre-qin-thought',
                        'names'    => ['zh-CN' => '先秦思想', 'en' => 'Pre-Qin thought'],
                    ],
                    [
                        'slug'     => 'confucianism',
                        'names'    => ['zh-CN' => '儒学', 'en' => 'Confucianism'],
                    ],
                    [
                        'slug'     => 'daoism',
                        'names'    => ['zh-CN' => '道家', 'en' => 'Daoism'],
                    ],
                    [
                        'slug'     => 'neo-confucianism',
                        'names'    => ['zh-CN' => '宋明理学', 'en' => 'Neo-Confucianism'],
                    ]
                ],
            ],
            [
                'slug'     => 'history-of-philosophy',
                'names'    => ['zh-CN' => '哲学史', 'en' => 'History of philosophy', 'ja' => '哲学史', 'ko' => '철학사', 'fr' => 'Histoire de la philosophie', 'de' => 'Philosophiegeschichte'],
                'children' => [
                    [
                        'slug'     => 'ancient-philosophy',
                        'names'    => ['zh-CN' => '古代哲学', 'en' => 'Ancient philosophy'],
                    ],
                    [
                        'slug'     => 'medieval-philosophy',
                        'names'    => ['zh-CN' => '中世纪哲学', 'en' => 'Medieval philosophy'],
                    ],
                    [
                        'slug'     => 'modern-philosophy',
                        'names'    => ['zh-CN' => '近代哲学', 'en' => 'Modern philosophy'],
                    ],
                    [
                        'slug'     => 'contemporary-philosophy',
                        'names'    => ['zh-CN' => '当代哲学', 'en' => 'Contemporary philosophy'],
                    ]
                ],
            ],
            [
                'slug'     => 'philosophy-of-religion',
                'names'    => ['zh-CN' => '宗教哲学', 'en' => 'Philosophy of religion', 'ja' => '宗教哲学', 'ko' => '종교철학', 'fr' => 'Philosophie de la religion', 'de' => 'Religionsphilosophie'],
                'children' => [
                    [
                        'slug'     => 'arguments-for-god',
                        'names'    => ['zh-CN' => '上帝存在的论证', 'en' => 'Arguments for God'],
                    ],
                    [
                        'slug'     => 'religious-epistemology',
                        'names'    => ['zh-CN' => '宗教认识论', 'en' => 'Religious epistemology'],
                    ]
                ],
            ]
        ],
    ],
    [
        'slug'     => 'mathematics',
        'names'    => ['zh-CN' => '数学', 'en' => 'Mathematics', 'ja' => '数学', 'ko' => '수학', 'fr' => 'Mathématiques', 'de' => 'Mathematik'],
        'children' => [
            [
                'slug'     => 'mathematical-logic',
                'names'    => ['zh-CN' => '数理逻辑', 'en' => 'Mathematical logic', 'ja' => '数理論理学', 'ko' => '수리논리학', 'fr' => 'Logique mathématique', 'de' => 'Mathematische Logik'],
                'children' => [
                    [
                        'slug'     => 'set-theory',
                        'names'    => ['zh-CN' => '集合论', 'en' => 'Set theory'],
                    ],
                    [
                        'slug'     => 'proof-theory',
                        'names'    => ['zh-CN' => '证明论', 'en' => 'Proof theory'],
                    ],
                    [
                        'slug'     => 'model-theory',
                        'names'    => ['zh-CN' => '模型论', 'en' => 'Model theory'],
                    ],
                    [
                        'slug'     => 'recursion-theory',
                        'names'    => ['zh-CN' => '递归论', 'en' => 'Recursion theory'],
                    ]
                ],
            ],
            [
                'slug'     => 'number-theory',
                'names'    => ['zh-CN' => '数论', 'en' => 'Number theory', 'ja' => '数論', 'ko' => '정수론', 'fr' => 'Théorie des nombres', 'de' => 'Zahlentheorie'],
                'children' => [
                    [
                        'slug'     => 'algebraic-number-theory',
                        'names'    => ['zh-CN' => '代数数论', 'en' => 'Algebraic number theory'],
                    ],
                    [
                        'slug'     => 'analytic-number-theory',
                        'names'    => ['zh-CN' => '解析数论', 'en' => 'Analytic number theory'],
                    ]
                ],
            ],
            [
                'slug'     => 'algebra',
                'names'    => ['zh-CN' => '代数学', 'en' => 'Algebra', 'ja' => '代数学', 'ko' => '대수학', 'fr' => 'Algèbre', 'de' => 'Algebra'],
                'children' => [
                    [
                        'slug'     => 'abstract-algebra',
                        'names'    => ['zh-CN' => '抽象代数', 'en' => 'Abstract algebra'],
                    ],
                    [
                        'slug'     => 'linear-algebra',
                        'names'    => ['zh-CN' => '线性代数', 'en' => 'Linear algebra'],
                    ],
                    [
                        'slug'     => 'commutative-algebra',
                        'names'    => ['zh-CN' => '交换代数', 'en' => 'Commutative algebra'],
                    ]
                ],
            ],
            [
                'slug'     => 'analysis',
                'names'    => ['zh-CN' => '分析学', 'en' => 'Analysis', 'ja' => '解析学', 'ko' => '해석학', 'fr' => 'Analyse', 'de' => 'Analysis'],
                'children' => [
                    [
                        'slug'     => 'real-analysis',
                        'names'    => ['zh-CN' => '实分析', 'en' => 'Real analysis'],
                    ],
                    [
                        'slug'     => 'complex-analysis',
                        'names'    => ['zh-CN' => '复分析', 'en' => 'Complex analysis'],
                    ],
                    [
                        'slug'     => 'functional-analysis',
                        'names'    => ['zh-CN' => '泛函分析', 'en' => 'Functional analysis'],
                    ]
                ],
            ],
            [
                'slug'     => 'geometry',
                'names'    => ['zh-CN' => '几何学', 'en' => 'Geometry', 'ja' => '幾何学', 'ko' => '기하학', 'fr' => 'Géométrie', 'de' => 'Geometrie'],
                'children' => [
                    [
                        'slug'     => 'differential-geometry',
                        'names'    => ['zh-CN' => '微分几何', 'en' => 'Differential geometry'],
                    ],
                    [
                        'slug'     => 'algebraic-geometry',
                        'names'    => ['zh-CN' => '代数几何', 'en' => 'Algebraic geometry'],
                    ]
                ],
            ],
            [
                'slug'     => 'topology',
                'names'    => ['zh-CN' => '拓扑学', 'en' => 'Topology', 'ja' => '位相幾何学', 'ko' => '위상수학', 'fr' => 'Topologie', 'de' => 'Topologie'],
                'children' => [
                    [
                        'slug'     => 'general-topology',
                        'names'    => ['zh-CN' => '一般拓扑学', 'en' => 'General topology'],
                    ],
                    [
                        'slug'     => 'algebraic-topology',
                        'names'    => ['zh-CN' => '代数拓扑', 'en' => 'Algebraic topology'],
                    ]
                ],
            ],
            [
                'slug'     => 'probability-theory',
                'names'    => ['zh-CN' => '概率论', 'en' => 'Probability theory', 'ja' => '確率論', 'ko' => '확률론', 'fr' => 'Théorie des probabilités', 'de' => 'Wahrscheinlichkeitstheorie'],
            ],
            [
                'slug'     => 'differential-equations',
                'names'    => ['zh-CN' => '微分方程', 'en' => 'Differential equations', 'ja' => '微分方程式', 'ko' => '미분방정식', 'fr' => 'Équations différentielles', 'de' => 'Differentialgleichungen'],
                'children' => [
                    [
                        'slug'     => 'ordinary-differential-equations',
                        'names'    => ['zh-CN' => '常微分方程', 'en' => 'Ordinary differential equations'],
                    ],
                    [
                        'slug'     => 'partial-differential-equations',
                        'names'    => ['zh-CN' => '偏微分方程', 'en' => 'Partial differential equations'],
                    ]
                ],
            ],
            [
                'slug'     => 'discrete-mathematics',
                'names'    => ['zh-CN' => '离散数学', 'en' => 'Discrete mathematics', 'ja' => '離散数学', 'ko' => '이산수학', 'fr' => 'Mathématiques discrètes', 'de' => 'Diskrete Mathematik'],
                'children' => [
                    [
                        'slug'     => 'combinatorics',
                        'names'    => ['zh-CN' => '组合数学', 'en' => 'Combinatorics'],
                    ],
                    [
                        'slug'     => 'graph-theory',
                        'names'    => ['zh-CN' => '图论', 'en' => 'Graph theory'],
                    ]
                ],
            ],
            [
                'slug'     => 'numerical-analysis',
                'names'    => ['zh-CN' => '数值分析', 'en' => 'Numerical analysis', 'ja' => '数値解析', 'ko' => '수치해석', 'fr' => 'Analyse numérique', 'de' => 'Numerische Mathematik'],
                'children' => [
                    [
                        'slug'     => 'numerical-optimization',
                        'names'    => ['zh-CN' => '数值最优化', 'en' => 'Numerical optimization'],
                    ]
                ],
            ],
            [
                'slug'     => 'history-of-mathematics',
                'names'    => ['zh-CN' => '数学史', 'en' => 'History of mathematics', 'ja' => '数学史', 'ko' => '수학사', 'fr' => 'Histoire des mathématiques', 'de' => 'Geschichte der Mathematik'],
            ]
        ],
    ],
    [
        'slug'     => 'physics',
        'names'    => ['zh-CN' => '物理学', 'en' => 'Physics', 'ja' => '物理学', 'ko' => '물리학', 'fr' => 'Physique', 'de' => 'Physik'],
        'children' => [
            [
                'slug'     => 'mechanics',
                'names'    => ['zh-CN' => '力学', 'en' => 'Mechanics', 'ja' => '力学', 'ko' => '역학', 'fr' => 'Mécanique', 'de' => 'Mechanik'],
                'children' => [
                    [
                        'slug'     => 'classical-mechanics',
                        'names'    => ['zh-CN' => '经典力学', 'en' => 'Classical mechanics'],
                    ],
                    [
                        'slug'     => 'fluid-mechanics',
                        'names'    => ['zh-CN' => '流体力学', 'en' => 'Fluid mechanics'],
                    ]
                ],
            ],
            [
                'slug'     => 'electromagnetism',
                'names'    => ['zh-CN' => '电磁学', 'en' => 'Electromagnetism', 'ja' => '電磁気学', 'ko' => '전자기학', 'fr' => 'Électromagnétisme', 'de' => 'Elektromagnetismus'],
                'children' => [
                    [
                        'slug'     => 'electrodynamics',
                        'names'    => ['zh-CN' => '电动力学', 'en' => 'Electrodynamics'],
                    ],
                    [
                        'slug'     => 'optics',
                        'names'    => ['zh-CN' => '光学', 'en' => 'Optics'],
                    ],
                    [
                        'slug'     => 'photonics',
                        'names'    => ['zh-CN' => '光子学', 'en' => 'Photonics'],
                    ]
                ],
            ],
            [
                'slug'     => 'thermodynamics',
                'names'    => ['zh-CN' => '热力学', 'en' => 'Thermodynamics', 'ja' => '熱力学', 'ko' => '열역학', 'fr' => 'Thermodynamique', 'de' => 'Thermodynamik'],
                'children' => [
                    [
                        'slug'     => 'statistical-mechanics',
                        'names'    => ['zh-CN' => '统计力学', 'en' => 'Statistical mechanics'],
                    ]
                ],
            ],
            [
                'slug'     => 'quantum-mechanics',
                'names'    => ['zh-CN' => '量子力学', 'en' => 'Quantum mechanics', 'ja' => '量子力学', 'ko' => '양자역학', 'fr' => 'Mécanique quantique', 'de' => 'Quantenmechanik'],
                'children' => [
                    [
                        'slug'     => 'quantum-information',
                        'names'    => ['zh-CN' => '量子信息', 'en' => 'Quantum information'],
                    ]
                ],
            ],
            [
                'slug'     => 'atomic-molecular-physics',
                'names'    => ['zh-CN' => '原子分子物理', 'en' => 'Atomic and molecular physics', 'ja' => '原子分子物理学', 'ko' => '원자분자물리학', 'fr' => 'Physique atomique et moléculaire', 'de' => 'Atom- und Molekülphysik'],
            ],
            [
                'slug'     => 'nuclear-physics',
                'names'    => ['zh-CN' => '核物理', 'en' => 'Nuclear physics', 'ja' => '原子核物理学', 'ko' => '핵물리학', 'fr' => 'Physique nucléaire', 'de' => 'Kernphysik'],
            ],
            [
                'slug'     => 'particle-physics',
                'names'    => ['zh-CN' => '粒子物理', 'en' => 'Particle physics', 'ja' => '素粒子物理学', 'ko' => '입자물리학', 'fr' => 'Physique des particules', 'de' => 'Teilchenphysik'],
                'children' => [
                    [
                        'slug'     => 'standard-model',
                        'names'    => ['zh-CN' => '标准模型', 'en' => 'Standard Model'],
                    ]
                ],
            ],
            [
                'slug'     => 'plasma-and-astrophysics',
                'names'    => ['zh-CN' => '等离子体与天体物理', 'en' => 'Plasma and astrophysics', 'ja' => 'プラズマ・天体物理学', 'ko' => '플라스마 및 천체물리학', 'fr' => 'Plasmas et astrophysique', 'de' => 'Plasma- und Astrophysik'],
                'children' => [
                    [
                        'slug'     => 'plasma-physics',
                        'names'    => ['zh-CN' => '等离子体物理', 'en' => 'Plasma physics'],
                    ],
                    [
                        'slug'     => 'astrophysics-and-cosmology',
                        'names'    => ['zh-CN' => '天体物理与宇宙学', 'en' => 'Astrophysics and cosmology'],
                    ]
                ],
            ],
            [
                'slug'     => 'computational-physics',
                'names'    => ['zh-CN' => '计算物理', 'en' => 'Computational physics', 'ja' => '計算物理', 'ko' => '계산물리학', 'fr' => 'Physique numérique', 'de' => 'Computational Physics'],
                'children' => [
                    [
                        'slug'     => 'theoretical-physics',
                        'names'    => ['zh-CN' => '理论物理', 'en' => 'Theoretical physics'],
                    ],
                    [
                        'slug'     => 'quantum-field-theory',
                        'names'    => ['zh-CN' => '量子场论', 'en' => 'Quantum field theory'],
                    ]
                ],
            ]
        ],
    ],
    [
        'slug'     => 'chemistry',
        'names'    => ['zh-CN' => '化学', 'en' => 'Chemistry', 'ja' => '化学', 'ko' => '화학', 'fr' => 'Chimie', 'de' => 'Chemie'],
        'children' => [
            [
                'slug'     => 'inorganic-chemistry',
                'names'    => ['zh-CN' => '无机化学', 'en' => 'Inorganic chemistry', 'ja' => '無機化学', 'ko' => '무기화학', 'fr' => 'Chimie inorganique', 'de' => 'Anorganische Chemie'],
                'children' => [
                    [
                        'slug'     => 'coordination-chemistry',
                        'names'    => ['zh-CN' => '配位化学', 'en' => 'Coordination chemistry'],
                    ]
                ],
            ],
            [
                'slug'     => 'analytical-chemistry',
                'names'    => ['zh-CN' => '分析化学', 'en' => 'Analytical chemistry', 'ja' => '分析化学', 'ko' => '분석화학', 'fr' => 'Chimie analytique', 'de' => 'Analytische Chemie'],
                'children' => [
                    [
                        'slug'     => 'instrumental-analysis',
                        'names'    => ['zh-CN' => '仪器分析', 'en' => 'Instrumental analysis'],
                    ]
                ],
            ],
            [
                'slug'     => 'physical-chemistry',
                'names'    => ['zh-CN' => '物理化学', 'en' => 'Physical chemistry', 'ja' => '物理化学', 'ko' => '물리화학', 'fr' => 'Chimie physique', 'de' => 'Physikalische Chemie'],
                'children' => [
                    [
                        'slug'     => 'chemical-thermodynamics',
                        'names'    => ['zh-CN' => '化学热力学', 'en' => 'Chemical thermodynamics'],
                    ],
                    [
                        'slug'     => 'chemical-kinetics',
                        'names'    => ['zh-CN' => '化学动力学', 'en' => 'Chemical kinetics'],
                    ]
                ],
            ],
            [
                'slug'     => 'computational-chemistry',
                'names'    => ['zh-CN' => '计算化学', 'en' => 'Computational chemistry', 'ja' => '計算化学', 'ko' => '계산화학', 'fr' => 'Chimie numérique', 'de' => 'Computerchemie'],
                'children' => [
                    [
                        'slug'     => 'quantum-chemistry',
                        'names'    => ['zh-CN' => '量子化学', 'en' => 'Quantum chemistry'],
                    ],
                    [
                        'slug'     => 'molecular-simulation',
                        'names'    => ['zh-CN' => '分子模拟', 'en' => 'Molecular simulation'],
                    ]
                ],
            ],
            [
                'slug'     => 'polymer-chemistry',
                'names'    => ['zh-CN' => '高分子化学', 'en' => 'Polymer chemistry', 'ja' => '高分子化学', 'ko' => '고분자화학', 'fr' => 'Chimie des polymères', 'de' => 'Polymerchemie'],
                'children' => [
                    [
                        'slug'     => 'polymer-synthesis',
                        'names'    => ['zh-CN' => '高分子合成', 'en' => 'Polymer synthesis'],
                    ]
                ],
            ],
            [
                'slug'     => 'biochemistry',
                'names'    => ['zh-CN' => '生物化学', 'en' => 'Biochemistry', 'ja' => '生化学', 'ko' => '생화학', 'fr' => 'Biochimie', 'de' => 'Biochemie'],
                'children' => [
                    [
                        'slug'     => 'enzymology',
                        'names'    => ['zh-CN' => '酶学', 'en' => 'Enzymology'],
                    ],
                    [
                        'slug'     => 'metabolic-biochemistry',
                        'names'    => ['zh-CN' => '代谢生化', 'en' => 'Metabolic biochemistry'],
                    ]
                ],
            ],
            [
                'slug'     => 'environmental-chemistry',
                'names'    => ['zh-CN' => '环境化学', 'en' => 'Environmental chemistry', 'ja' => '環境化学', 'ko' => '환경화학', 'fr' => 'Chimie environnementale', 'de' => 'Umweltchemie'],
                'children' => [
                    [
                        'slug'     => 'atmospheric-chemistry',
                        'names'    => ['zh-CN' => '大气化学', 'en' => 'Atmospheric chemistry'],
                    ]
                ],
            ],
            [
                'slug'     => 'medicinal-chemistry',
                'names'    => ['zh-CN' => '药物化学', 'en' => 'Medicinal chemistry', 'ja' => '医薬化学', 'ko' => '의약화학', 'fr' => 'Chimie médicinale', 'de' => 'Medizinische Chemie'],
            ]
        ],
    ],
    [
        'slug'     => 'biology',
        'names'    => ['zh-CN' => '生物学', 'en' => 'Biology', 'ja' => '生物学', 'ko' => '생물학', 'fr' => 'Biologie', 'de' => 'Biologie'],
        'children' => [
            [
                'slug'     => 'molecular-and-cellular-biology',
                'names'    => ['zh-CN' => '分子与细胞生物学', 'en' => 'Molecular and cellular biology', 'ja' => '分子細胞生物学', 'ko' => '분자세포생물학', 'fr' => 'Biologie moléculaire et cellulaire', 'de' => 'Molekular- und Zellbiologie'],
                'children' => [
                    [
                        'slug'     => 'molecular-biology',
                        'names'    => ['zh-CN' => '分子生物学', 'en' => 'Molecular biology'],
                    ],
                    [
                        'slug'     => 'cell-biology',
                        'names'    => ['zh-CN' => '细胞生物学', 'en' => 'Cell biology'],
                    ]
                ],
            ],
            [
                'slug'     => 'genetics',
                'names'    => ['zh-CN' => '遗传学', 'en' => 'Genetics', 'ja' => '遺伝学', 'ko' => '유전학', 'fr' => 'Génétique', 'de' => 'Genetik'],
                'children' => [
                    [
                        'slug'     => 'population-genetics',
                        'names'    => ['zh-CN' => '群体遗传学', 'en' => 'Population genetics'],
                    ],
                    [
                        'slug'     => 'genomics',
                        'names'    => ['zh-CN' => '基因组学', 'en' => 'Genomics'],
                    ]
                ],
            ],
            [
                'slug'     => 'biochemistry-and-biophysics',
                'names'    => ['zh-CN' => '生物化学与生物物理', 'en' => 'Biochemistry and biophysics', 'ja' => '生化学・生物物理学', 'ko' => '생화학 및 생물물리학', 'fr' => 'Biochimie et biophysique', 'de' => 'Biochemie und Biophysik'],
                'children' => [
                    [
                        'slug'     => 'biophysics',
                        'names'    => ['zh-CN' => '生物物理学', 'en' => 'Biophysics'],
                    ],
                    [
                        'slug'     => 'structural-biology',
                        'names'    => ['zh-CN' => '结构生物学', 'en' => 'Structural biology'],
                    ]
                ],
            ],
            [
                'slug'     => 'immunology',
                'names'    => ['zh-CN' => '免疫学', 'en' => 'Immunology', 'ja' => '免疫学', 'ko' => '면역학', 'fr' => 'Immunologie', 'de' => 'Immunologie'],
                'children' => [
                    [
                        'slug'     => 'cellular-immunology',
                        'names'    => ['zh-CN' => '细胞免疫学', 'en' => 'Cellular immunology'],
                    ]
                ],
            ],
            [
                'slug'     => 'neuroscience',
                'names'    => ['zh-CN' => '神经科学', 'en' => 'Neuroscience', 'ja' => '神経科学', 'ko' => '신경과학', 'fr' => 'Neurosciences', 'de' => 'Neurowissenschaften'],
                'children' => [
                    [
                        'slug'     => 'cognitive-neuroscience',
                        'names'    => ['zh-CN' => '认知神经科学', 'en' => 'Cognitive neuroscience'],
                    ]
                ],
            ],
            [
                'slug'     => 'ecology',
                'names'    => ['zh-CN' => '生态学', 'en' => 'Ecology', 'ja' => '生態学', 'ko' => '생태학', 'fr' => 'Écologie', 'de' => 'Ökologie'],
                'children' => [
                    [
                        'slug'     => 'ecosystem-ecology',
                        'names'    => ['zh-CN' => '生态系统生态学', 'en' => 'Ecosystem ecology'],
                    ],
                    [
                        'slug'     => 'conservation-biology',
                        'names'    => ['zh-CN' => '保护生物学', 'en' => 'Conservation biology'],
                    ]
                ],
            ],
            [
                'slug'     => 'evolutionary-biology',
                'names'    => ['zh-CN' => '进化生物学', 'en' => 'Evolutionary biology', 'ja' => '進化生物学', 'ko' => '진화생물학', 'fr' => 'Biologie de l’évolution', 'de' => 'Evolutionsbiologie'],
                'children' => [
                    [
                        'slug'     => 'phylogenetics',
                        'names'    => ['zh-CN' => '系统发生学', 'en' => 'Phylogenetics'],
                    ]
                ],
            ],
            [
                'slug'     => 'physiology',
                'names'    => ['zh-CN' => '生理学', 'en' => 'Physiology', 'ja' => '生理学', 'ko' => '생리학', 'fr' => 'Physiologie', 'de' => 'Physiologie'],
                'children' => [
                    [
                        'slug'     => 'plant-physiology',
                        'names'    => ['zh-CN' => '植物生理学', 'en' => 'Plant physiology'],
                    ]
                ],
            ],
            [
                'slug'     => 'developmental-biology',
                'names'    => ['zh-CN' => '发育生物学', 'en' => 'Developmental biology', 'ja' => '発生生物学', 'ko' => '발생생물학', 'fr' => 'Biologie du développement', 'de' => 'Entwicklungsbiologie'],
                'children' => [
                    [
                        'slug'     => 'embryology',
                        'names'    => ['zh-CN' => '胚胎学', 'en' => 'Embryology'],
                    ]
                ],
            ],
            [
                'slug'     => 'botany',
                'names'    => ['zh-CN' => '植物学', 'en' => 'Botany', 'ja' => '植物学', 'ko' => '식물학', 'fr' => 'Botanique', 'de' => 'Botanik'],
            ],
            [
                'slug'     => 'zoology',
                'names'    => ['zh-CN' => '动物学', 'en' => 'Zoology', 'ja' => '動物学', 'ko' => '동물학', 'fr' => 'Zoologie', 'de' => 'Zoologie'],
                'children' => [
                    [
                        'slug'     => 'entomology',
                        'names'    => ['zh-CN' => '昆虫学', 'en' => 'Entomology'],
                    ]
                ],
            ],
            [
                'slug'     => 'marine-biology',
                'names'    => ['zh-CN' => '海洋生物学', 'en' => 'Marine biology', 'ja' => '海洋生物学', 'ko' => '해양생물학', 'fr' => 'Biologie marine', 'de' => 'Meeresbiologie'],
            ],
            [
                'slug'     => 'bioinformatics',
                'names'    => ['zh-CN' => '生物信息学', 'en' => 'Bioinformatics', 'ja' => 'バイオインフォマティクス', 'ko' => '생물정보학', 'fr' => 'Bio-informatique', 'de' => 'Bioinformatik'],
                'children' => [
                    [
                        'slug'     => 'computational-biology',
                        'names'    => ['zh-CN' => '计算生物学', 'en' => 'Computational biology'],
                    ]
                ],
            ]
        ],
    ],
    [
        'slug'     => 'computer-science',
        'names'    => ['zh-CN' => '计算机科学', 'en' => 'Computer science', 'ja' => '計算機科学', 'ko' => '컴퓨터과학', 'fr' => 'Informatique', 'de' => 'Informatik'],
        'children' => [
            [
                'slug'     => 'algorithms',
                'names'    => ['zh-CN' => '算法', 'en' => 'Algorithms', 'ja' => 'アルゴリズム', 'ko' => '알고리즘', 'fr' => 'Algorithmes', 'de' => 'Algorithmen'],
                'children' => [
                    [
                        'slug'     => 'algorithm-design',
                        'names'    => ['zh-CN' => '算法设计', 'en' => 'Algorithm design'],
                    ],
                    [
                        'slug'     => 'computational-complexity',
                        'names'    => ['zh-CN' => '计算复杂性', 'en' => 'Computational complexity'],
                    ]
                ],
            ],
            [
                'slug'     => 'theory-of-computation',
                'names'    => ['zh-CN' => '计算理论', 'en' => 'Theory of computation', 'ja' => '計算理論', 'ko' => '계산 이론', 'fr' => 'Théorie de la calculabilité', 'de' => 'Theoretische Informatik'],
                'children' => [
                    [
                        'slug'     => 'computability',
                        'names'    => ['zh-CN' => '可计算性', 'en' => 'Computability'],
                    ]
                ],
            ],
            [
                'slug'     => 'programming-languages',
                'names'    => ['zh-CN' => '程序设计语言', 'en' => 'Programming languages', 'ja' => 'プログラミング言語', 'ko' => '프로그래밍 언어', 'fr' => 'Langages de programmation', 'de' => 'Programmiersprachen'],
                'children' => [
                    [
                        'slug'     => 'compiler-design',
                        'names'    => ['zh-CN' => '编译原理', 'en' => 'Compiler design'],
                    ]
                ],
            ],
            [
                'slug'     => 'software-engineering',
                'names'    => ['zh-CN' => '软件工程', 'en' => 'Software engineering', 'ja' => 'ソフトウェア工学', 'ko' => '소프트웨어공학', 'fr' => 'Génie logiciel', 'de' => 'Softwaretechnik'],
                'children' => [
                    [
                        'slug'     => 'software-testing',
                        'names'    => ['zh-CN' => '软件测试', 'en' => 'Software testing'],
                    ]
                ],
            ],
            [
                'slug'     => 'databases',
                'names'    => ['zh-CN' => '数据库', 'en' => 'Databases', 'ja' => 'データベース', 'ko' => '데이터베이스', 'fr' => 'Bases de données', 'de' => 'Datenbanken'],
                'children' => [
                    [
                        'slug'     => 'database-systems',
                        'names'    => ['zh-CN' => '数据库系统', 'en' => 'Database systems'],
                    ],
                    [
                        'slug'     => 'query-processing',
                        'names'    => ['zh-CN' => '查询处理', 'en' => 'Query processing'],
                    ],
                    [
                        'slug'     => 'database-design',
                        'names'    => ['zh-CN' => '数据库设计', 'en' => 'Database design'],
                    ]
                ],
            ],
            [
                'slug'     => 'computer-networks',
                'names'    => ['zh-CN' => '计算机网络', 'en' => 'Computer networks', 'ja' => 'コンピュータネットワーク', 'ko' => '컴퓨터 네트워크', 'fr' => 'Réseaux informatiques', 'de' => 'Rechnernetze'],
                'children' => [
                    [
                        'slug'     => 'network-security',
                        'names'    => ['zh-CN' => '网络安全', 'en' => 'Network security'],
                    ]
                ],
            ],
            [
                'slug'     => 'computer-architecture',
                'names'    => ['zh-CN' => '计算机体系结构', 'en' => 'Computer architecture', 'ja' => 'コンピュータアーキテクチャ', 'ko' => '컴퓨터 구조', 'fr' => 'Architecture des ordinateurs', 'de' => 'Rechnerarchitektur'],
                'children' => [
                    [
                        'slug'     => 'embedded-systems',
                        'names'    => ['zh-CN' => '嵌入式系统', 'en' => 'Embedded systems'],
                    ]
                ],
            ],
            [
                'slug'     => 'operating-systems',
                'names'    => ['zh-CN' => '操作系统', 'en' => 'Operating systems', 'ja' => 'オペレーティングシステム', 'ko' => '운영체제', 'fr' => 'Systèmes d’exploitation', 'de' => 'Betriebssysteme'],
                'children' => [
                    [
                        'slug'     => 'distributed-systems',
                        'names'    => ['zh-CN' => '分布式系统', 'en' => 'Distributed systems'],
                    ]
                ],
            ],
            [
                'slug'     => 'computer-graphics',
                'names'    => ['zh-CN' => '计算机图形学', 'en' => 'Computer graphics', 'ja' => 'コンピュータグラフィックス', 'ko' => '컴퓨터 그래픽스', 'fr' => 'Infographie', 'de' => 'Computergrafik'],
                'children' => [
                    [
                        'slug'     => 'image-processing',
                        'names'    => ['zh-CN' => '图像处理', 'en' => 'Image processing'],
                    ]
                ],
            ],
            [
                'slug'     => 'human-computer-interaction',
                'names'    => ['zh-CN' => '人机交互', 'en' => 'Human-computer interaction', 'ja' => 'ヒューマンコンピュータインタラクション', 'ko' => '인간-컴퓨터 상호작용', 'fr' => 'Interaction homme-machine', 'de' => 'Mensch-Computer-Interaktion'],
                'children' => [
                    [
                        'slug'     => 'user-interface-design',
                        'names'    => ['zh-CN' => '用户界面设计', 'en' => 'User interface design'],
                    ],
                    [
                        'slug'     => 'computer-supported-cooperative-work',
                        'names'    => ['zh-CN' => '计算机支持的协同工作', 'en' => 'Computer-supported cooperative work'],
                    ]
                ],
            ],
            [
                'slug'     => 'information-systems',
                'names'    => ['zh-CN' => '信息系统', 'en' => 'Information systems', 'ja' => '情報システム', 'ko' => '정보시스템', 'fr' => 'Systèmes d’information', 'de' => 'Informationssysteme'],
                'children' => [
                    [
                        'slug'     => 'information-retrieval',
                        'names'    => ['zh-CN' => '信息检索', 'en' => 'Information retrieval'],
                    ],
                    [
                        'slug'     => 'information-security',
                        'names'    => ['zh-CN' => '信息安全', 'en' => 'Information security'],
                    ]
                ],
            ]
        ],
    ],
    [
        'slug'     => 'artificial-intelligence',
        'names'    => ['zh-CN' => '人工智能', 'en' => 'Artificial intelligence', 'ja' => '人工知能', 'ko' => '인공지능', 'fr' => 'Intelligence artificielle', 'de' => 'Künstliche Intelligenz'],
        'children' => [
            [
                'slug'     => 'machine-learning',
                'names'    => ['zh-CN' => '机器学习', 'en' => 'Machine learning', 'ja' => '機械学習', 'ko' => '기계학습', 'fr' => 'Apprentissage automatique', 'de' => 'Maschinelles Lernen'],
                'children' => [
                    [
                        'slug'     => 'deep-learning',
                        'names'    => ['zh-CN' => '深度学习', 'en' => 'Deep learning'],
                    ],
                    [
                        'slug'     => 'generative-ai',
                        'names'    => ['zh-CN' => '生成式人工智能', 'en' => 'Generative AI'],
                    ],
                    [
                        'slug'     => 'multimodal-learning',
                        'names'    => ['zh-CN' => '多模态学习', 'en' => 'Multimodal learning'],
                    ],
                    [
                        'slug'     => 'reinforcement-learning',
                        'names'    => ['zh-CN' => '强化学习', 'en' => 'Reinforcement learning'],
                    ],
                    [
                        'slug'     => 'supervised-learning',
                        'names'    => ['zh-CN' => '监督学习', 'en' => 'Supervised learning'],
                    ]
                ],
            ],
            [
                'slug'     => 'natural-language-processing',
                'names'    => ['zh-CN' => '自然语言处理', 'en' => 'Natural language processing', 'ja' => '自然言語処理', 'ko' => '자연어처리', 'fr' => 'Traitement automatique du langage naturel', 'de' => 'Computerlinguistik'],
                'children' => [
                    [
                        'slug'     => 'computational-linguistics',
                        'names'    => ['zh-CN' => '计算语言学', 'en' => 'Computational linguistics'],
                    ]
                ],
            ],
            [
                'slug'     => 'computer-vision',
                'names'    => ['zh-CN' => '计算机视觉', 'en' => 'Computer vision', 'ja' => 'コンピュータビジョン', 'ko' => '컴퓨터 비전', 'fr' => 'Vision par ordinateur', 'de' => 'Computer Vision'],
            ],
            [
                'slug'     => 'knowledge-representation',
                'names'    => ['zh-CN' => '知识表示', 'en' => 'Knowledge representation', 'ja' => '知識表現', 'ko' => '지식 표현', 'fr' => 'Représentation des connaissances', 'de' => 'Wissensrepräsentation'],
                'children' => [
                    [
                        'slug'     => 'knowledge-graphs',
                        'names'    => ['zh-CN' => '知识图谱', 'en' => 'Knowledge graphs'],
                    ]
                ],
            ],
            [
                'slug'     => 'automated-reasoning',
                'names'    => ['zh-CN' => '自动推理', 'en' => 'Automated reasoning', 'ja' => '自動推論', 'ko' => '자동 추론', 'fr' => 'Raisonnement automatique', 'de' => 'Automatisches Schließen'],
                'children' => [
                    [
                        'slug'     => 'automated-planning',
                        'names'    => ['zh-CN' => '自动规划', 'en' => 'Automated planning'],
                    ]
                ],
            ],
            [
                'slug'     => 'ai-robotics',
                'names'    => ['zh-CN' => '智能机器人', 'en' => 'AI robotics', 'ja' => '知能ロボティクス', 'ko' => '지능 로보틱스', 'fr' => 'Robotique intelligente', 'de' => 'Intelligente Robotik'],
                'children' => [
                    [
                        'slug'     => 'autonomous-systems',
                        'names'    => ['zh-CN' => '自主系统', 'en' => 'Autonomous systems'],
                    ]
                ],
            ],
            [
                'slug'     => 'speech-processing',
                'names'    => ['zh-CN' => '语音处理', 'en' => 'Speech processing', 'ja' => '音声処理', 'ko' => '음성처리', 'fr' => 'Traitement de la parole', 'de' => 'Sprachverarbeitung'],
                'children' => [
                    [
                        'slug'     => 'speech-recognition',
                        'names'    => ['zh-CN' => '语音识别', 'en' => 'Speech recognition'],
                    ]
                ],
            ],
            [
                'slug'     => 'ai-ethics',
                'names'    => ['zh-CN' => '人工智能伦理', 'en' => 'AI ethics', 'ja' => 'AI倫理', 'ko' => '인공지능 윤리', 'fr' => 'Éthique de l’IA', 'de' => 'KI-Ethik'],
                'children' => [
                    [
                        'slug'     => 'explainable-ai',
                        'names'    => ['zh-CN' => '可解释人工智能', 'en' => 'Explainable AI'],
                    ]
                ],
            ],
            [
                'slug'     => 'intelligent-systems',
                'names'    => ['zh-CN' => '智能系统', 'en' => 'Intelligent systems', 'ja' => '知能システム', 'ko' => '지능형 시스템', 'fr' => 'Systèmes intelligents', 'de' => 'Intelligente Systeme'],
            ]
        ],
    ],
    [
        'slug'     => 'medicine',
        'names'    => ['zh-CN' => '医学', 'en' => 'Medicine', 'ja' => '医学', 'ko' => '의학', 'fr' => 'Médecine', 'de' => 'Medizin'],
        'children' => [
            [
                'slug'     => 'surgery',
                'names'    => ['zh-CN' => '外科学', 'en' => 'Surgery', 'ja' => '外科学', 'ko' => '외과학', 'fr' => 'Chirurgie', 'de' => 'Chirurgie'],
                'children' => [
                    [
                        'slug'     => 'general-surgery',
                        'names'    => ['zh-CN' => '普通外科', 'en' => 'General surgery'],
                    ],
                    [
                        'slug'     => 'orthopedic-surgery',
                        'names'    => ['zh-CN' => '骨科学', 'en' => 'Orthopedic surgery'],
                    ]
                ],
            ],
            [
                'slug'     => 'pediatrics',
                'names'    => ['zh-CN' => '儿科学', 'en' => 'Pediatrics', 'ja' => '小児科学', 'ko' => '소아과학', 'fr' => 'Pédiatrie', 'de' => 'Pädiatrie'],
                'children' => [
                    [
                        'slug'     => 'neonatology',
                        'names'    => ['zh-CN' => '新生儿学', 'en' => 'Neonatology'],
                    ]
                ],
            ],
            [
                'slug'     => 'obstetrics-and-gynecology',
                'names'    => ['zh-CN' => '妇产科学', 'en' => 'Obstetrics and gynecology', 'ja' => '産婦人科学', 'ko' => '산부인과학', 'fr' => 'Obstétrique et gynécologie', 'de' => 'Geburtshilfe und Gynäkologie'],
                'children' => [
                    [
                        'slug'     => 'obstetrics',
                        'names'    => ['zh-CN' => '产科学', 'en' => 'Obstetrics'],
                    ],
                    [
                        'slug'     => 'maternal-fetal-medicine',
                        'names'    => ['zh-CN' => '母胎医学', 'en' => 'Maternal-fetal medicine'],
                    ]
                ],
            ],
            [
                'slug'     => 'epidemiology-and-public-health',
                'names'    => ['zh-CN' => '流行病学与公共卫生', 'en' => 'Epidemiology and public health', 'ja' => '疫学・公衆衛生学', 'ko' => '역학 및 공중보건', 'fr' => 'Épidémiologie et santé publique', 'de' => 'Epidemiologie und Public Health'],
                'children' => [
                    [
                        'slug'     => 'epidemiology',
                        'names'    => ['zh-CN' => '流行病学', 'en' => 'Epidemiology'],
                    ],
                    [
                        'slug'     => 'health-policy',
                        'names'    => ['zh-CN' => '卫生政策', 'en' => 'Health policy'],
                    ]
                ],
            ],
            [
                'slug'     => 'medical-imaging',
                'names'    => ['zh-CN' => '医学影像学', 'en' => 'Medical imaging', 'ja' => '医用画像工学', 'ko' => '의료영상', 'fr' => 'Imagerie médicale', 'de' => 'Medizinische Bildgebung'],
                'children' => [
                    [
                        'slug'     => 'radiology',
                        'names'    => ['zh-CN' => '放射学', 'en' => 'Radiology'],
                    ]
                ],
            ],
            [
                'slug'     => 'psychiatry',
                'names'    => ['zh-CN' => '精神病学', 'en' => 'Psychiatry', 'ja' => '精神医学', 'ko' => '정신의학', 'fr' => 'Psychiatrie', 'de' => 'Psychiatrie'],
                'children' => [
                    [
                        'slug'     => 'child-and-adolescent-psychiatry',
                        'names'    => ['zh-CN' => '儿童青少年精神病学', 'en' => 'Child and adolescent psychiatry'],
                    ]
                ],
            ],
            [
                'slug'     => 'anesthesiology-and-emergency-medicine',
                'names'    => ['zh-CN' => '麻醉与急诊医学', 'en' => 'Anesthesiology and emergency medicine', 'ja' => '麻酔・救急医学', 'ko' => '마취 및 응급의학', 'fr' => 'Anesthésie et médecine d’urgence', 'de' => 'Anästhesie und Notfallmedizin'],
                'children' => [
                    [
                        'slug'     => 'anesthesiology',
                        'names'    => ['zh-CN' => '麻醉学', 'en' => 'Anesthesiology'],
                    ],
                    [
                        'slug'     => 'emergency-medicine',
                        'names'    => ['zh-CN' => '急诊医学', 'en' => 'Emergency medicine'],
                    ]
                ],
            ],
            [
                'slug'     => 'oncology',
                'names'    => ['zh-CN' => '肿瘤学', 'en' => 'Oncology', 'ja' => '腫瘍学', 'ko' => '종양학', 'fr' => 'Oncologie', 'de' => 'Onkologie'],
                'children' => [
                    [
                        'slug'     => 'clinical-oncology',
                        'names'    => ['zh-CN' => '临床肿瘤学', 'en' => 'Clinical oncology'],
                    ]
                ],
            ],
            [
                'slug'     => 'nursing',
                'names'    => ['zh-CN' => '护理学', 'en' => 'Nursing', 'ja' => '看護学', 'ko' => '간호학', 'fr' => 'Sciences infirmières', 'de' => 'Pflegewissenschaft'],
                'children' => [
                    [
                        'slug'     => 'community-health-nursing',
                        'names'    => ['zh-CN' => '社区护理学', 'en' => 'Community health nursing'],
                    ]
                ],
            ],
            [
                'slug'     => 'dentistry',
                'names'    => ['zh-CN' => '口腔医学', 'en' => 'Dentistry', 'ja' => '歯学', 'ko' => '치의학', 'fr' => 'Médecine dentaire', 'de' => 'Zahnmedizin'],
                'children' => [
                    [
                        'slug'     => 'orthodontics',
                        'names'    => ['zh-CN' => '口腔正畸学', 'en' => 'Orthodontics'],
                    ]
                ],
            ],
            [
                'slug'     => 'traditional-chinese-medicine',
                'names'    => ['zh-CN' => '中医学', 'en' => 'Traditional Chinese medicine', 'ja' => '中医学', 'ko' => '중의학', 'fr' => 'Médecine traditionnelle chinoise', 'de' => 'Traditionelle chinesische Medizin'],
                'children' => [
                    [
                        'slug'     => 'acupuncture',
                        'names'    => ['zh-CN' => '针灸学', 'en' => 'Acupuncture'],
                    ]
                ],
            ]
        ],
    ],
    [
        'slug'     => 'engineering',
        'names'    => ['zh-CN' => '工程学', 'en' => 'Engineering', 'ja' => '工学', 'ko' => '공학', 'fr' => 'Ingénierie', 'de' => 'Ingenieurwissenschaften'],
        'children' => [
            [
                'slug'     => 'mechanical-engineering',
                'names'    => ['zh-CN' => '机械工程', 'en' => 'Mechanical engineering', 'ja' => '機械工学', 'ko' => '기계공학', 'fr' => 'Génie mécanique', 'de' => 'Maschinenbau'],
                'children' => [
                    [
                        'slug'     => 'manufacturing-engineering',
                        'names'    => ['zh-CN' => '制造工程', 'en' => 'Manufacturing engineering'],
                    ],
                    [
                        'slug'     => 'mechatronics',
                        'names'    => ['zh-CN' => '机电一体化', 'en' => 'Mechatronics'],
                    ]
                ],
            ],
            [
                'slug'     => 'civil-engineering',
                'names'    => ['zh-CN' => '土木工程', 'en' => 'Civil engineering', 'ja' => '土木工学', 'ko' => '토목공학', 'fr' => 'Génie civil', 'de' => 'Bauingenieurwesen'],
                'children' => [
                    [
                        'slug'     => 'structural-engineering',
                        'names'    => ['zh-CN' => '结构工程', 'en' => 'Structural engineering'],
                    ],
                    [
                        'slug'     => 'geotechnical-engineering',
                        'names'    => ['zh-CN' => '岩土工程', 'en' => 'Geotechnical engineering'],
                    ]
                ],
            ],
            [
                'slug'     => 'electrical-engineering',
                'names'    => ['zh-CN' => '电气工程', 'en' => 'Electrical engineering', 'ja' => '電気工学', 'ko' => '전기공학', 'fr' => 'Génie électrique', 'de' => 'Elektrotechnik'],
                'children' => [
                    [
                        'slug'     => 'power-systems',
                        'names'    => ['zh-CN' => '电力系统', 'en' => 'Power systems'],
                    ],
                    [
                        'slug'     => 'electronics',
                        'names'    => ['zh-CN' => '电子学', 'en' => 'Electronics'],
                    ]
                ],
            ],
            [
                'slug'     => 'chemical-engineering',
                'names'    => ['zh-CN' => '化学工程', 'en' => 'Chemical engineering', 'ja' => '化学工学', 'ko' => '화학공학', 'fr' => 'Génie chimique', 'de' => 'Verfahrenstechnik'],
                'children' => [
                    [
                        'slug'     => 'transport-phenomena',
                        'names'    => ['zh-CN' => '传递过程', 'en' => 'Transport phenomena'],
                    ],
                    [
                        'slug'     => 'separation-processes',
                        'names'    => ['zh-CN' => '分离工程', 'en' => 'Separation processes'],
                    ]
                ],
            ],
            [
                'slug'     => 'aerospace-engineering',
                'names'    => ['zh-CN' => '航空航天工程', 'en' => 'Aerospace engineering', 'ja' => '航空宇宙工学', 'ko' => '항공우주공학', 'fr' => 'Génie aérospatial', 'de' => 'Luft- und Raumfahrttechnik'],
                'children' => [
                    [
                        'slug'     => 'aerodynamics',
                        'names'    => ['zh-CN' => '空气动力学', 'en' => 'Aerodynamics'],
                    ]
                ],
            ],
            [
                'slug'     => 'biomedical-engineering',
                'names'    => ['zh-CN' => '生物医学工程', 'en' => 'Biomedical engineering', 'ja' => '生体医工学', 'ko' => '의공학', 'fr' => 'Génie biomédical', 'de' => 'Biomedizintechnik'],
                'children' => [
                    [
                        'slug'     => 'biomechanics',
                        'names'    => ['zh-CN' => '生物力学', 'en' => 'Biomechanics'],
                    ],
                    [
                        'slug'     => 'biomaterials-engineering',
                        'names'    => ['zh-CN' => '生物材料工程', 'en' => 'Biomaterials engineering'],
                    ]
                ],
            ],
            [
                'slug'     => 'control-science-and-engineering',
                'names'    => ['zh-CN' => '控制科学与工程', 'en' => 'Control science and engineering', 'ja' => '制御工学', 'ko' => '제어공학', 'fr' => 'Automatique', 'de' => 'Regelungstechnik'],
                'children' => [
                    [
                        'slug'     => 'control-theory',
                        'names'    => ['zh-CN' => '控制理论', 'en' => 'Control theory'],
                    ],
                    [
                        'slug'     => 'systems-engineering',
                        'names'    => ['zh-CN' => '系统工程', 'en' => 'Systems engineering'],
                    ]
                ],
            ],
            [
                'slug'     => 'energy-and-power-engineering',
                'names'    => ['zh-CN' => '能源与动力工程', 'en' => 'Energy and power engineering', 'ja' => 'エネルギー・動力工学', 'ko' => '에너지 및 동력공학', 'fr' => 'Génie énergétique', 'de' => 'Energie- und Kraftwerkstechnik'],
                'children' => [
                    [
                        'slug'     => 'renewable-energy',
                        'names'    => ['zh-CN' => '可再生能源', 'en' => 'Renewable energy'],
                    ],
                    [
                        'slug'     => 'energy-storage',
                        'names'    => ['zh-CN' => '储能技术', 'en' => 'Energy storage'],
                    ]
                ],
            ],
            [
                'slug'     => 'environmental-engineering',
                'names'    => ['zh-CN' => '环境工程', 'en' => 'Environmental engineering', 'ja' => '環境工学', 'ko' => '환경공학', 'fr' => 'Génie de l’environnement', 'de' => 'Umwelttechnik'],
                'children' => [
                    [
                        'slug'     => 'water-treatment',
                        'names'    => ['zh-CN' => '水处理', 'en' => 'Water treatment'],
                    ]
                ],
            ],
            [
                'slug'     => 'industrial-engineering',
                'names'    => ['zh-CN' => '工业工程', 'en' => 'Industrial engineering', 'ja' => '経営工学', 'ko' => '산업공학', 'fr' => 'Génie industriel', 'de' => 'Wirtschaftsingenieurwesen'],
                'children' => [
                    [
                        'slug'     => 'human-factors-engineering',
                        'names'    => ['zh-CN' => '人因工程', 'en' => 'Human factors engineering'],
                    ]
                ],
            ],
            [
                'slug'     => 'nuclear-engineering',
                'names'    => ['zh-CN' => '核工程', 'en' => 'Nuclear engineering', 'ja' => '原子力工学', 'ko' => '원자력공학', 'fr' => 'Génie nucléaire', 'de' => 'Kerntechnik'],
                'children' => [
                    [
                        'slug'     => 'reactor-physics',
                        'names'    => ['zh-CN' => '反应堆物理', 'en' => 'Reactor physics'],
                    ]
                ],
            ],
            [
                'slug'     => 'surveying-and-geodesy-engineering',
                'names'    => ['zh-CN' => '测绘工程', 'en' => 'Surveying and geomatics engineering', 'ja' => '測量工学', 'ko' => '측량공학', 'fr' => 'Génie géomatique', 'de' => 'Geodäsie und Vermessung'],
                'children' => [
                    [
                        'slug'     => 'geodesy',
                        'names'    => ['zh-CN' => '大地测量学', 'en' => 'Geodesy'],
                    ],
                    [
                        'slug'     => 'engineering-surveying',
                        'names'    => ['zh-CN' => '工程测量', 'en' => 'Engineering surveying'],
                    ]
                ],
            ]
        ],
    ],
    [
        'slug'     => 'materials-science',
        'names'    => ['zh-CN' => '材料科学', 'en' => 'Materials science', 'ja' => '材料科学', 'ko' => '재료과학', 'fr' => 'Science des matériaux', 'de' => 'Materialwissenschaft'],
        'children' => [
            [
                'slug'     => 'metallic-materials',
                'names'    => ['zh-CN' => '金属材料', 'en' => 'Metallic materials', 'ja' => '金属材料', 'ko' => '금속재료', 'fr' => 'Matériaux métalliques', 'de' => 'Metallische Werkstoffe'],
                'children' => [
                    [
                        'slug'     => 'nonferrous-metals',
                        'names'    => ['zh-CN' => '有色金属', 'en' => 'Nonferrous metals'],
                    ]
                ],
            ],
            [
                'slug'     => 'inorganic-nonmetallic-materials',
                'names'    => ['zh-CN' => '无机非金属材料', 'en' => 'Inorganic nonmetallic materials', 'ja' => '無機非金属材料', 'ko' => '무기 비금속 재료', 'fr' => 'Matériaux inorganiques non métalliques', 'de' => 'Anorganische nichtmetallische Werkstoffe'],
                'children' => [
                    [
                        'slug'     => 'ceramics',
                        'names'    => ['zh-CN' => '陶瓷材料', 'en' => 'Ceramics'],
                    ],
                    [
                        'slug'     => 'cement-and-concrete',
                        'names'    => ['zh-CN' => '水泥与混凝土', 'en' => 'Cement and concrete'],
                    ]
                ],
            ],
            [
                'slug'     => 'polymer-materials',
                'names'    => ['zh-CN' => '高分子材料', 'en' => 'Polymer materials', 'ja' => '高分子材料', 'ko' => '고분자 재료', 'fr' => 'Matériaux polymères', 'de' => 'Polymere Werkstoffe'],
            ],
            [
                'slug'     => 'composite-materials',
                'names'    => ['zh-CN' => '复合材料', 'en' => 'Composite materials', 'ja' => '複合材料', 'ko' => '복합재료', 'fr' => 'Matériaux composites', 'de' => 'Verbundwerkstoffe'],
            ],
            [
                'slug'     => 'nanomaterials',
                'names'    => ['zh-CN' => '纳米材料', 'en' => 'Nanomaterials', 'ja' => 'ナノ材料', 'ko' => '나노소재', 'fr' => 'Nanomatériaux', 'de' => 'Nanomaterialien'],
                'children' => [
                    [
                        'slug'     => 'carbon-materials',
                        'names'    => ['zh-CN' => '碳材料', 'en' => 'Carbon materials'],
                    ],
                    [
                        'slug'     => 'nanofabrication',
                        'names'    => ['zh-CN' => '纳米制备', 'en' => 'Nanofabrication'],
                    ]
                ],
            ],
            [
                'slug'     => 'thin-films',
                'names'    => ['zh-CN' => '薄膜材料', 'en' => 'Thin films', 'ja' => '薄膜材料', 'ko' => '박막재료', 'fr' => 'Couches minces', 'de' => 'Dünnschichten'],
            ],
            [
                'slug'     => 'computational-materials-science',
                'names'    => ['zh-CN' => '计算材料学', 'en' => 'Computational materials science', 'ja' => '計算材料科学', 'ko' => '계산재료과학', 'fr' => 'Science numérique des matériaux', 'de' => 'Computational Materials Science'],
            ],
            [
                'slug'     => 'functional-materials',
                'names'    => ['zh-CN' => '功能材料', 'en' => 'Functional materials', 'ja' => '機能性材料', 'ko' => '기능성 재료', 'fr' => 'Matériaux fonctionnels', 'de' => 'Funktionswerkstoffe'],
                'children' => [
                    [
                        'slug'     => 'electronic-materials',
                        'names'    => ['zh-CN' => '电子材料', 'en' => 'Electronic materials'],
                    ],
                    [
                        'slug'     => 'magnetic-materials',
                        'names'    => ['zh-CN' => '磁性材料', 'en' => 'Magnetic materials'],
                    ],
                    [
                        'slug'     => 'energy-materials',
                        'names'    => ['zh-CN' => '能源材料', 'en' => 'Energy materials'],
                    ],
                    [
                        'slug'     => 'biomaterials',
                        'names'    => ['zh-CN' => '生物材料', 'en' => 'Biomaterials'],
                    ],
                    [
                        'slug'     => 'optical-materials',
                        'names'    => ['zh-CN' => '光学材料', 'en' => 'Optical materials'],
                    ]
                ],
            ]
        ],
    ],
    [
        'slug'     => 'earth-science',
        'names'    => ['zh-CN' => '地球科学', 'en' => 'Earth science', 'ja' => '地球科学', 'ko' => '지구과학', 'fr' => 'Sciences de la Terre', 'de' => 'Geowissenschaften'],
        'children' => [
            [
                'slug'     => 'geology',
                'names'    => ['zh-CN' => '地质学', 'en' => 'Geology', 'ja' => '地質学', 'ko' => '지질학', 'fr' => 'Géologie', 'de' => 'Geologie'],
                'children' => [
                    [
                        'slug'     => 'mineralogy',
                        'names'    => ['zh-CN' => '矿物学', 'en' => 'Mineralogy'],
                    ],
                    [
                        'slug'     => 'petrology',
                        'names'    => ['zh-CN' => '岩石学', 'en' => 'Petrology'],
                    ],
                    [
                        'slug'     => 'structural-geology',
                        'names'    => ['zh-CN' => '构造地质学', 'en' => 'Structural geology'],
                    ]
                ],
            ],
            [
                'slug'     => 'geophysics',
                'names'    => ['zh-CN' => '地球物理学', 'en' => 'Geophysics', 'ja' => '地球物理学', 'ko' => '지구물리학', 'fr' => 'Géophysique', 'de' => 'Geophysik'],
                'children' => [
                    [
                        'slug'     => 'seismology',
                        'names'    => ['zh-CN' => '地震学', 'en' => 'Seismology'],
                    ],
                    [
                        'slug'     => 'geomagnetism',
                        'names'    => ['zh-CN' => '地磁学', 'en' => 'Geomagnetism'],
                    ]
                ],
            ],
            [
                'slug'     => 'atmospheric-science',
                'names'    => ['zh-CN' => '大气科学', 'en' => 'Atmospheric science', 'ja' => '大気科学', 'ko' => '대기과학', 'fr' => 'Sciences de l’atmosphère', 'de' => 'Atmosphärenwissenschaften'],
                'children' => [
                    [
                        'slug'     => 'meteorology',
                        'names'    => ['zh-CN' => '气象学', 'en' => 'Meteorology'],
                    ]
                ],
            ],
            [
                'slug'     => 'oceanography',
                'names'    => ['zh-CN' => '海洋科学', 'en' => 'Oceanography', 'ja' => '海洋学', 'ko' => '해양학', 'fr' => 'Océanographie', 'de' => 'Ozeanographie'],
                'children' => [
                    [
                        'slug'     => 'chemical-oceanography',
                        'names'    => ['zh-CN' => '海洋化学', 'en' => 'Chemical oceanography'],
                    ]
                ],
            ],
            [
                'slug'     => 'hydrology',
                'names'    => ['zh-CN' => '水文学', 'en' => 'Hydrology', 'ja' => '水文学', 'ko' => '수문학', 'fr' => 'Hydrologie', 'de' => 'Hydrologie'],
            ],
            [
                'slug'     => 'glaciology',
                'names'    => ['zh-CN' => '冰川学', 'en' => 'Glaciology', 'ja' => '氷河学', 'ko' => '빙하학', 'fr' => 'Glaciologie', 'de' => 'Glaziologie'],
            ],
            [
                'slug'     => 'soil-science',
                'names'    => ['zh-CN' => '土壤学', 'en' => 'Soil science', 'ja' => '土壌学', 'ko' => '토양학', 'fr' => 'Science du sol', 'de' => 'Bodenkunde'],
                'children' => [
                    [
                        'slug'     => 'pedology',
                        'names'    => ['zh-CN' => '土壤地理学', 'en' => 'Pedology'],
                    ]
                ],
            ],
            [
                'slug'     => 'geographic-information-science',
                'names'    => ['zh-CN' => '地理信息科学', 'en' => 'Geographic information science', 'ja' => '地理情報科学', 'ko' => '지리정보과학', 'fr' => 'Géomatique', 'de' => 'Geoinformatik'],
                'children' => [
                    [
                        'slug'     => 'cartography',
                        'names'    => ['zh-CN' => '地图学', 'en' => 'Cartography'],
                    ],
                    [
                        'slug'     => 'digital-elevation-modeling',
                        'names'    => ['zh-CN' => '数字地形分析', 'en' => 'Digital elevation modeling'],
                    ]
                ],
            ]
        ],
    ],
    [
        'slug'     => 'astronomy',
        'names'    => ['zh-CN' => '天文学', 'en' => 'Astronomy', 'ja' => '天文学', 'ko' => '천문학', 'fr' => 'Astronomie', 'de' => 'Astronomie'],
        'children' => [
            [
                'slug'     => 'observational-astronomy',
                'names'    => ['zh-CN' => '观测天文学', 'en' => 'Observational astronomy', 'ja' => '観測天文学', 'ko' => '관측천문학', 'fr' => 'Astronomie d’observation', 'de' => 'Beobachtende Astronomie'],
                'children' => [
                    [
                        'slug'     => 'astronomical-spectroscopy',
                        'names'    => ['zh-CN' => '天体光谱学', 'en' => 'Astronomical spectroscopy'],
                    ]
                ],
            ],
            [
                'slug'     => 'astrophysics',
                'names'    => ['zh-CN' => '天体物理学', 'en' => 'Astrophysics', 'ja' => '天体物理学', 'ko' => '천체물리학', 'fr' => 'Astrophysique', 'de' => 'Astrophysik'],
                'children' => [
                    [
                        'slug'     => 'stellar-astrophysics',
                        'names'    => ['zh-CN' => '恒星物理', 'en' => 'Stellar astrophysics'],
                    ],
                    [
                        'slug'     => 'galactic-astronomy',
                        'names'    => ['zh-CN' => '星系天文学', 'en' => 'Galactic astronomy'],
                    ]
                ],
            ],
            [
                'slug'     => 'cosmology-and-astroparticle-physics',
                'names'    => ['zh-CN' => '宇宙学与粒子天体物理', 'en' => 'Cosmology and astroparticle physics', 'ja' => '宇宙論・宇宙素粒子物理学', 'ko' => '우주론 및 입자천체물리학', 'fr' => 'Cosmologie et physique des astroparticules', 'de' => 'Kosmologie und Astroteilchenphysik'],
                'children' => [
                    [
                        'slug'     => 'physical-cosmology',
                        'names'    => ['zh-CN' => '物理宇宙学', 'en' => 'Physical cosmology'],
                    ]
                ],
            ],
            [
                'slug'     => 'planetary-science',
                'names'    => ['zh-CN' => '行星科学', 'en' => 'Planetary science', 'ja' => '惑星科学', 'ko' => '행성과학', 'fr' => 'Sciences planétaires', 'de' => 'Planetenwissenschaften'],
                'children' => [
                    [
                        'slug'     => 'planetary-geology',
                        'names'    => ['zh-CN' => '行星地质学', 'en' => 'Planetary geology'],
                    ]
                ],
            ],
            [
                'slug'     => 'astrochemistry-and-astrobiology',
                'names'    => ['zh-CN' => '天体化学与天体生物学', 'en' => 'Astrochemistry and astrobiology', 'ja' => '宇宙化学・宇宙生物学', 'ko' => '천체화학 및 천체생물학', 'fr' => 'Astrochimie et astrobiologie', 'de' => 'Astrochemie und Astrobiologie'],
                'children' => [
                    [
                        'slug'     => 'astrochemistry',
                        'names'    => ['zh-CN' => '天体化学', 'en' => 'Astrochemistry'],
                    ],
                    [
                        'slug'     => 'astrobiology',
                        'names'    => ['zh-CN' => '天体生物学', 'en' => 'Astrobiology'],
                    ]
                ],
            ],
            [
                'slug'     => 'high-energy-astrophysics',
                'names'    => ['zh-CN' => '高能天体物理', 'en' => 'High energy astrophysics', 'ja' => '高エネルギー天体物理学', 'ko' => '고에너지 천체물리학', 'fr' => 'Astrophysique des hautes énergies', 'de' => 'Hochenergie-Astrophysik'],
            ]
        ],
    ],
    [
        'slug'     => 'economics',
        'names'    => ['zh-CN' => '经济学', 'en' => 'Economics', 'ja' => '経済学', 'ko' => '경제학', 'fr' => 'Économie', 'de' => 'Wirtschaftswissenschaften'],
        'children' => [
            [
                'slug'     => 'microeconomics',
                'names'    => ['zh-CN' => '微观经济学', 'en' => 'Microeconomics', 'ja' => 'ミクロ経済学', 'ko' => '미시경제학', 'fr' => 'Microéconomie', 'de' => 'Mikroökonomie'],
                'children' => [
                    [
                        'slug'     => 'market-structure',
                        'names'    => ['zh-CN' => '市场结构', 'en' => 'Market structure'],
                    ]
                ],
            ],
            [
                'slug'     => 'econometrics',
                'names'    => ['zh-CN' => '计量经济学', 'en' => 'Econometrics', 'ja' => '計量経済学', 'ko' => '계량경제학', 'fr' => 'Économétrie', 'de' => 'Ökonometrie'],
                'children' => [
                    [
                        'slug'     => 'time-series-analysis',
                        'names'    => ['zh-CN' => '时间序列分析', 'en' => 'Time series analysis'],
                    ]
                ],
            ],
            [
                'slug'     => 'international-economics',
                'names'    => ['zh-CN' => '国际经济学', 'en' => 'International economics', 'ja' => '国際経済学', 'ko' => '국제경제학', 'fr' => 'Économie internationale', 'de' => 'Außenwirtschaft'],
                'children' => [
                    [
                        'slug'     => 'international-trade',
                        'names'    => ['zh-CN' => '国际贸易', 'en' => 'International trade'],
                    ]
                ],
            ],
            [
                'slug'     => 'public-economics',
                'names'    => ['zh-CN' => '公共经济学', 'en' => 'Public economics', 'ja' => '公共経済学', 'ko' => '공공경제학', 'fr' => 'Économie publique', 'de' => 'Finanzwissenschaft'],
                'children' => [
                    [
                        'slug'     => 'taxation',
                        'names'    => ['zh-CN' => '税收', 'en' => 'Taxation'],
                    ]
                ],
            ],
            [
                'slug'     => 'labor-economics',
                'names'    => ['zh-CN' => '劳动经济学', 'en' => 'Labor economics', 'ja' => '労働経済学', 'ko' => '노동경제학', 'fr' => 'Économie du travail', 'de' => 'Arbeitsökonomik'],
                'children' => [
                    [
                        'slug'     => 'human-capital',
                        'names'    => ['zh-CN' => '人力资本', 'en' => 'Human capital'],
                    ],
                    [
                        'slug'     => 'wage-determination',
                        'names'    => ['zh-CN' => '工资决定', 'en' => 'Wage determination'],
                    ],
                    [
                        'slug'     => 'employment-and-unemployment',
                        'names'    => ['zh-CN' => '就业与失业', 'en' => 'Employment and unemployment'],
                    ]
                ],
            ],
            [
                'slug'     => 'development-economics',
                'names'    => ['zh-CN' => '发展经济学', 'en' => 'Development economics', 'ja' => '開発経済学', 'ko' => '개발경제학', 'fr' => 'Économie du développement', 'de' => 'Entwicklungsökonomie'],
            ],
            [
                'slug'     => 'monetary-economics',
                'names'    => ['zh-CN' => '货币经济学', 'en' => 'Monetary economics', 'ja' => '貨幣経済学', 'ko' => '화폐경제학', 'fr' => 'Économie monétaire', 'de' => 'Geldtheorie'],
            ],
            [
                'slug'     => 'industrial-organization',
                'names'    => ['zh-CN' => '产业组织', 'en' => 'Industrial organization', 'ja' => '産業組織論', 'ko' => '산업조직론', 'fr' => 'Économie industrielle', 'de' => 'Industrieökonomik'],
            ],
            [
                'slug'     => 'behavioral-economics',
                'names'    => ['zh-CN' => '行为经济学', 'en' => 'Behavioral economics', 'ja' => '行動経済学', 'ko' => '행동경제학', 'fr' => 'Économie comportementale', 'de' => 'Verhaltensökonomik'],
            ],
            [
                'slug'     => 'financial-economics',
                'names'    => ['zh-CN' => '金融经济学', 'en' => 'Financial economics', 'ja' => '金融経済学', 'ko' => '금융경제학', 'fr' => 'Économie financière', 'de' => 'Finanzökonomik'],
                'children' => [
                    [
                        'slug'     => 'corporate-finance',
                        'names'    => ['zh-CN' => '公司金融', 'en' => 'Corporate finance'],
                    ]
                ],
            ]
        ],
    ],
    [
        'slug'     => 'management',
        'names'    => ['zh-CN' => '管理学', 'en' => 'Management', 'ja' => '経営学', 'ko' => '경영학', 'fr' => 'Sciences de gestion', 'de' => 'Betriebswirtschaftslehre'],
        'children' => [
            [
                'slug'     => 'human-resource-management',
                'names'    => ['zh-CN' => '人力资源管理', 'en' => 'Human resource management', 'ja' => '人的資源管理', 'ko' => '인적자원관리', 'fr' => 'Gestion des ressources humaines', 'de' => 'Personalmanagement'],
                'children' => [
                    [
                        'slug'     => 'organizational-behavior',
                        'names'    => ['zh-CN' => '组织行为学', 'en' => 'Organizational behavior'],
                    ]
                ],
            ],
            [
                'slug'     => 'strategic-management',
                'names'    => ['zh-CN' => '战略管理', 'en' => 'Strategic management', 'ja' => '経営戦略', 'ko' => '전략경영', 'fr' => 'Management stratégique', 'de' => 'Strategisches Management'],
            ],
            [
                'slug'     => 'operations-management',
                'names'    => ['zh-CN' => '运营管理', 'en' => 'Operations management', 'ja' => '生産管理', 'ko' => '운영관리', 'fr' => 'Gestion des opérations', 'de' => 'Produktionsmanagement'],
                'children' => [
                    [
                        'slug'     => 'supply-chain-management',
                        'names'    => ['zh-CN' => '供应链管理', 'en' => 'Supply chain management'],
                    ]
                ],
            ],
            [
                'slug'     => 'entrepreneurship',
                'names'    => ['zh-CN' => '创业管理', 'en' => 'Entrepreneurship', 'ja' => 'アントレプレナーシップ', 'ko' => '창업학', 'fr' => 'Entrepreneuriat', 'de' => 'Entrepreneurship'],
            ],
            [
                'slug'     => 'international-business',
                'names'    => ['zh-CN' => '国际商务', 'en' => 'International business', 'ja' => '国際ビジネス', 'ko' => '국제경영', 'fr' => 'Commerce international', 'de' => 'Internationales Management'],
            ],
            [
                'slug'     => 'project-management',
                'names'    => ['zh-CN' => '项目管理', 'en' => 'Project management', 'ja' => 'プロジェクトマネジメント', 'ko' => '프로젝트 관리', 'fr' => 'Gestion de projet', 'de' => 'Projektmanagement'],
            ]
        ],
    ],
    [
        'slug'     => 'accounting',
        'names'    => ['zh-CN' => '会计学', 'en' => 'Accounting', 'ja' => '会計学', 'ko' => '회계학', 'fr' => 'Comptabilité', 'de' => 'Rechnungswesen'],
        'children' => [
            [
                'slug'     => 'financial-accounting',
                'names'    => ['zh-CN' => '财务会计', 'en' => 'Financial accounting', 'ja' => '財務会計', 'ko' => '재무회계', 'fr' => 'Comptabilité financière', 'de' => 'Finanzbuchhaltung'],
            ],
            [
                'slug'     => 'management-accounting',
                'names'    => ['zh-CN' => '管理会计', 'en' => 'Management accounting', 'ja' => '管理会計', 'ko' => '관리회계', 'fr' => 'Comptabilité de gestion', 'de' => 'Controlling'],
            ],
            [
                'slug'     => 'auditing',
                'names'    => ['zh-CN' => '审计学', 'en' => 'Auditing', 'ja' => '監査論', 'ko' => '회계감사', 'fr' => 'Audit', 'de' => 'Wirtschaftsprüfung'],
            ]
        ],
    ],
    [
        'slug'     => 'finance',
        'names'    => ['zh-CN' => '金融学', 'en' => 'Finance', 'ja' => '金融学', 'ko' => '금융학', 'fr' => 'Finance', 'de' => 'Finanzwirtschaft'],
        'children' => [
            [
                'slug'     => 'corporate-finance-and-governance',
                'names'    => ['zh-CN' => '公司金融与治理', 'en' => 'Corporate finance and governance', 'ja' => 'コーポレートファイナンス', 'ko' => '기업금융', 'fr' => 'Finance d’entreprise', 'de' => 'Unternehmensfinanzierung'],
            ],
            [
                'slug'     => 'asset-pricing',
                'names'    => ['zh-CN' => '资产定价', 'en' => 'Asset pricing', 'ja' => '資産価格理論', 'ko' => '자산가격결정', 'fr' => 'Évaluation des actifs', 'de' => 'Asset Pricing'],
            ],
            [
                'slug'     => 'investment',
                'names'    => ['zh-CN' => '投资学', 'en' => 'Investment', 'ja' => '投資学', 'ko' => '투자론', 'fr' => 'Investissement', 'de' => 'Investitionslehre'],
            ],
            [
                'slug'     => 'financial-engineering',
                'names'    => ['zh-CN' => '金融工程', 'en' => 'Financial engineering', 'ja' => '金融工学', 'ko' => '금융공학', 'fr' => 'Ingénierie financière', 'de' => 'Financial Engineering'],
            ],
            [
                'slug'     => 'financial-risk-management',
                'names'    => ['zh-CN' => '金融风险管理', 'en' => 'Financial risk management', 'ja' => '金融リスク管理', 'ko' => '금융리스크 관리', 'fr' => 'Gestion des risques financiers', 'de' => 'Finanzrisikomanagement'],
            ],
            [
                'slug'     => 'behavioral-finance',
                'names'    => ['zh-CN' => '行为金融学', 'en' => 'Behavioral finance', 'ja' => '行動ファイナンス', 'ko' => '행태재무론', 'fr' => 'Finance comportementale', 'de' => 'Behavioral Finance'],
            ]
        ],
    ],
    [
        'slug'     => 'psychology',
        'names'    => ['zh-CN' => '心理学', 'en' => 'Psychology', 'ja' => '心理学', 'ko' => '심리학', 'fr' => 'Psychologie', 'de' => 'Psychologie'],
        'children' => [
            [
                'slug'     => 'developmental-psychology',
                'names'    => ['zh-CN' => '发展心理学', 'en' => 'Developmental psychology', 'ja' => '発達心理学', 'ko' => '발달심리학', 'fr' => 'Psychologie du développement', 'de' => 'Entwicklungspsychologie'],
                'children' => [
                    [
                        'slug'     => 'child-development',
                        'names'    => ['zh-CN' => '儿童发展', 'en' => 'Child development'],
                    ]
                ],
            ],
            [
                'slug'     => 'social-psychology',
                'names'    => ['zh-CN' => '社会心理学', 'en' => 'Social psychology', 'ja' => '社会心理学', 'ko' => '사회심리학', 'fr' => 'Psychologie sociale', 'de' => 'Sozialpsychologie'],
            ],
            [
                'slug'     => 'clinical-psychology',
                'names'    => ['zh-CN' => '临床心理学', 'en' => 'Clinical psychology', 'ja' => '臨床心理学', 'ko' => '임상심리학', 'fr' => 'Psychologie clinique', 'de' => 'Klinische Psychologie'],
                'children' => [
                    [
                        'slug'     => 'psychopathology',
                        'names'    => ['zh-CN' => '精神病理学', 'en' => 'Psychopathology'],
                    ]
                ],
            ],
            [
                'slug'     => 'biological-psychology',
                'names'    => ['zh-CN' => '生理心理学', 'en' => 'Biological psychology', 'ja' => '生理心理学', 'ko' => '생물심리학', 'fr' => 'Psychobiologie', 'de' => 'Biologische Psychologie'],
            ],
            [
                'slug'     => 'personality-psychology',
                'names'    => ['zh-CN' => '人格心理学', 'en' => 'Personality psychology', 'ja' => 'パーソナリティ心理学', 'ko' => '성격심리학', 'fr' => 'Psychologie de la personnalité', 'de' => 'Persönlichkeitspsychologie'],
            ],
            [
                'slug'     => 'educational-psychology',
                'names'    => ['zh-CN' => '教育心理学', 'en' => 'Educational psychology', 'ja' => '教育心理学', 'ko' => '교육심리학', 'fr' => 'Psychologie de l’éducation', 'de' => 'Pädagogische Psychologie'],
                'children' => [
                    [
                        'slug'     => 'learning-sciences',
                        'names'    => ['zh-CN' => '学习科学', 'en' => 'Learning sciences'],
                    ]
                ],
            ],
            [
                'slug'     => 'industrial-organizational-psychology',
                'names'    => ['zh-CN' => '工业与组织心理学', 'en' => 'Industrial and organizational psychology', 'ja' => '産業・組織心理学', 'ko' => '산업 및 조직심리학', 'fr' => 'Psychologie du travail', 'de' => 'Arbeits- und Organisationspsychologie'],
            ],
            [
                'slug'     => 'health-psychology',
                'names'    => ['zh-CN' => '健康心理学', 'en' => 'Health psychology', 'ja' => '健康心理学', 'ko' => '건강심리학', 'fr' => 'Psychologie de la santé', 'de' => 'Gesundheitspsychologie'],
            ],
            [
                'slug'     => 'psychometrics',
                'names'    => ['zh-CN' => '心理测量学', 'en' => 'Psychometrics', 'ja' => '心理測定学', 'ko' => '심리측정학', 'fr' => 'Psychométrie', 'de' => 'Psychometrie'],
                'children' => [
                    [
                        'slug'     => 'structural-equation-modeling',
                        'names'    => ['zh-CN' => '结构方程模型', 'en' => 'Structural equation modeling'],
                    ]
                ],
            ],
            [
                'slug'     => 'counseling-psychology',
                'names'    => ['zh-CN' => '咨询心理学', 'en' => 'Counseling psychology', 'ja' => 'カウンセリング心理学', 'ko' => '상담심리학', 'fr' => 'Psychologie du counseling', 'de' => 'Beratungspsychologie'],
            ]
        ],
    ],
    [
        'slug'     => 'linguistics',
        'names'    => ['zh-CN' => '语言学', 'en' => 'Linguistics', 'ja' => '言語学', 'ko' => '언어학', 'fr' => 'Linguistique', 'de' => 'Sprachwissenschaft'],
        'children' => [
            [
                'slug'     => 'phonology',
                'names'    => ['zh-CN' => '音系学', 'en' => 'Phonology', 'ja' => '音韻論', 'ko' => '음운론', 'fr' => 'Phonologie', 'de' => 'Phonologie'],
            ],
            [
                'slug'     => 'syntax',
                'names'    => ['zh-CN' => '句法学', 'en' => 'Syntax', 'ja' => '統語論', 'ko' => '통사론', 'fr' => 'Syntaxe', 'de' => 'Syntax'],
                'children' => [
                    [
                        'slug'     => 'generative-syntax',
                        'names'    => ['zh-CN' => '生成句法', 'en' => 'Generative syntax'],
                    ]
                ],
            ],
            [
                'slug'     => 'semantics',
                'names'    => ['zh-CN' => '语义学', 'en' => 'Semantics', 'ja' => '意味論', 'ko' => '의미론', 'fr' => 'Sémantique', 'de' => 'Semantik'],
                'children' => [
                    [
                        'slug'     => 'formal-semantics',
                        'names'    => ['zh-CN' => '形式语义学', 'en' => 'Formal semantics'],
                    ]
                ],
            ],
            [
                'slug'     => 'morphology',
                'names'    => ['zh-CN' => '形态学', 'en' => 'Morphology', 'ja' => '形態論', 'ko' => '형태론', 'fr' => 'Morphologie', 'de' => 'Morphologie'],
                'children' => [
                    [
                        'slug'     => 'inflection-and-derivation',
                        'names'    => ['zh-CN' => '屈折与派生', 'en' => 'Inflection and derivation'],
                    ]
                ],
            ],
            [
                'slug'     => 'pragmatics',
                'names'    => ['zh-CN' => '语用学', 'en' => 'Pragmatics', 'ja' => '語用論', 'ko' => '화용론', 'fr' => 'Pragmatique', 'de' => 'Pragmatik'],
            ],
            [
                'slug'     => 'sociolinguistics',
                'names'    => ['zh-CN' => '社会语言学', 'en' => 'Sociolinguistics', 'ja' => '社会言語学', 'ko' => '사회언어학', 'fr' => 'Sociolinguistique', 'de' => 'Soziolinguistik'],
            ],
            [
                'slug'     => 'psycholinguistics',
                'names'    => ['zh-CN' => '心理语言学', 'en' => 'Psycholinguistics', 'ja' => '心理言語学', 'ko' => '심리언어학', 'fr' => 'Psycholinguistique', 'de' => 'Psycholinguistik'],
            ],
            [
                'slug'     => 'computational-linguistics-methods',
                'names'    => ['zh-CN' => '计算语言学方法', 'en' => 'Computational linguistics methods', 'ja' => '計量言語学', 'ko' => '전산언어학', 'fr' => 'Méthodes de linguistique computationnelle', 'de' => 'Computerlinguistische Methoden'],
                'children' => [
                    [
                        'slug'     => 'corpus-linguistics',
                        'names'    => ['zh-CN' => '语料库语言学', 'en' => 'Corpus linguistics'],
                    ],
                    [
                        'slug'     => 'translation-technology',
                        'names'    => ['zh-CN' => '翻译技术', 'en' => 'Translation technology'],
                    ]
                ],
            ],
            [
                'slug'     => 'historical-linguistics',
                'names'    => ['zh-CN' => '历史语言学', 'en' => 'Historical linguistics', 'ja' => '歴史言語学', 'ko' => '역사언어학', 'fr' => 'Linguistique historique', 'de' => 'Historische Linguistik'],
                'children' => [
                    [
                        'slug'     => 'comparative-linguistics',
                        'names'    => ['zh-CN' => '比较语言学', 'en' => 'Comparative linguistics'],
                    ]
                ],
            ],
            [
                'slug'     => 'applied-linguistics',
                'names'    => ['zh-CN' => '应用语言学', 'en' => 'Applied linguistics', 'ja' => '応用言語学', 'ko' => '응용언어학', 'fr' => 'Linguistique appliquée', 'de' => 'Angewandte Linguistik'],
                'children' => [
                    [
                        'slug'     => 'second-language-acquisition',
                        'names'    => ['zh-CN' => '第二语言习得', 'en' => 'Second language acquisition'],
                    ]
                ],
            ],
            [
                'slug'     => 'linguistic-typology',
                'names'    => ['zh-CN' => '语言类型学', 'en' => 'Linguistic typology', 'ja' => '言語類型論', 'ko' => '언어유형론', 'fr' => 'Typologie linguistique', 'de' => 'Sprachtypologie'],
            ]
        ],
    ],
    [
        'slug'     => 'history',
        'names'    => ['zh-CN' => '历史学', 'en' => 'History', 'ja' => '歴史学', 'ko' => '역사학', 'fr' => 'Histoire', 'de' => 'Geschichtswissenschaft'],
        'children' => [
            [
                'slug'     => 'world-history',
                'names'    => ['zh-CN' => '世界史', 'en' => 'World history', 'ja' => '世界史', 'ko' => '세계사', 'fr' => 'Histoire mondiale', 'de' => 'Weltgeschichte'],
                'children' => [
                    [
                        'slug'     => 'global-history',
                        'names'    => ['zh-CN' => '全球史', 'en' => 'Global history'],
                    ]
                ],
            ],
            [
                'slug'     => 'chinese-history',
                'names'    => ['zh-CN' => '中国史', 'en' => 'Chinese history', 'ja' => '中国史', 'ko' => '중국사', 'fr' => 'Histoire de la Chine', 'de' => 'Geschichte Chinas'],
                'children' => [
                    [
                        'slug'     => 'modern-chinese-history',
                        'names'    => ['zh-CN' => '中国近现代史', 'en' => 'Modern Chinese history'],
                    ]
                ],
            ],
            [
                'slug'     => 'european-history',
                'names'    => ['zh-CN' => '欧洲史', 'en' => 'European history', 'ja' => 'ヨーロッパ史', 'ko' => '유럽사', 'fr' => 'Histoire de l’Europe', 'de' => 'Geschichte Europas'],
            ],
            [
                'slug'     => 'modern-history',
                'names'    => ['zh-CN' => '近代史', 'en' => 'Modern history', 'ja' => '近代史', 'ko' => '근대사', 'fr' => 'Histoire moderne', 'de' => 'Neuere Geschichte'],
                'children' => [
                    [
                        'slug'     => 'contemporary-history',
                        'names'    => ['zh-CN' => '当代史', 'en' => 'Contemporary history'],
                    ]
                ],
            ],
            [
                'slug'     => 'ancient-history',
                'names'    => ['zh-CN' => '古代史', 'en' => 'Ancient history', 'ja' => '古代史', 'ko' => '고대사', 'fr' => 'Histoire ancienne', 'de' => 'Alte Geschichte'],
            ],
            [
                'slug'     => 'medieval-history',
                'names'    => ['zh-CN' => '中世纪史', 'en' => 'Medieval history', 'ja' => '中世史', 'ko' => '중세사', 'fr' => 'Histoire médiévale', 'de' => 'Mittelalterliche Geschichte'],
            ],
            [
                'slug'     => 'history-of-science-and-technology',
                'names'    => ['zh-CN' => '科学技术史', 'en' => 'History of science and technology', 'ja' => '科学技術史', 'ko' => '과학기술사', 'fr' => 'Histoire des sciences et des techniques', 'de' => 'Wissenschafts- und Technikgeschichte'],
                'children' => [
                    [
                        'slug'     => 'history-of-technology',
                        'names'    => ['zh-CN' => '技术史', 'en' => 'History of technology'],
                    ]
                ],
            ],
            [
                'slug'     => 'economic-and-social-history',
                'names'    => ['zh-CN' => '经济与社会史', 'en' => 'Economic and social history', 'ja' => '経済社会史', 'ko' => '경제사회사', 'fr' => 'Histoire économique et sociale', 'de' => 'Wirtschafts- und Sozialgeschichte'],
            ],
            [
                'slug'     => 'cultural-history',
                'names'    => ['zh-CN' => '文化史', 'en' => 'Cultural history', 'ja' => '文化史', 'ko' => '문화사', 'fr' => 'Histoire culturelle', 'de' => 'Kulturgeschichte'],
            ],
            [
                'slug'     => 'art-history',
                'names'    => ['zh-CN' => '艺术史', 'en' => 'Art history', 'ja' => '美術史', 'ko' => '미술사', 'fr' => 'Histoire de l’art', 'de' => 'Kunstgeschichte'],
                'children' => [
                    [
                        'slug'     => 'history-of-architecture',
                        'names'    => ['zh-CN' => '建筑史', 'en' => 'Architectural history'],
                    ]
                ],
            ],
            [
                'slug'     => 'historiography',
                'names'    => ['zh-CN' => '史学理论', 'en' => 'Historiography', 'ja' => '史学史', 'ko' => '사학사', 'fr' => 'Historiographie', 'de' => 'Historiographie'],
                'children' => [
                    [
                        'slug'     => 'historical-methodology',
                        'names'    => ['zh-CN' => '历史方法论', 'en' => 'Historical methodology'],
                    ]
                ],
            ]
        ],
    ],
    [
        'slug'     => 'literature',
        'names'    => ['zh-CN' => '文学', 'en' => 'Literature', 'ja' => '文学', 'ko' => '문학', 'fr' => 'Littérature', 'de' => 'Literaturwissenschaft'],
        'children' => [
            [
                'slug'     => 'chinese-literature',
                'names'    => ['zh-CN' => '中国文学', 'en' => 'Chinese literature', 'ja' => '中国文学', 'ko' => '중국문학', 'fr' => 'Littérature chinoise', 'de' => 'Chinesische Literatur'],
                'children' => [
                    [
                        'slug'     => 'classical-chinese-literature',
                        'names'    => ['zh-CN' => '中国古代文学', 'en' => 'Classical Chinese literature'],
                    ],
                    [
                        'slug'     => 'modern-chinese-literature',
                        'names'    => ['zh-CN' => '中国现当代文学', 'en' => 'Modern Chinese literature'],
                    ]
                ],
            ],
            [
                'slug'     => 'english-and-american-literature',
                'names'    => ['zh-CN' => '英美文学', 'en' => 'English and American literature', 'ja' => '英米文学', 'ko' => '영미문학', 'fr' => 'Littérature anglaise et américaine', 'de' => 'Englische und amerikanische Literatur'],
                'children' => [
                    [
                        'slug'     => 'english-literature',
                        'names'    => ['zh-CN' => '英国文学', 'en' => 'English literature'],
                    ],
                    [
                        'slug'     => 'american-literature',
                        'names'    => ['zh-CN' => '美国文学', 'en' => 'American literature'],
                    ]
                ],
            ],
            [
                'slug'     => 'comparative-literature',
                'names'    => ['zh-CN' => '比较文学', 'en' => 'Comparative literature', 'ja' => '比較文学', 'ko' => '비교문학', 'fr' => 'Littérature comparée', 'de' => 'Komparatistik'],
                'children' => [
                    [
                        'slug'     => 'translation-studies',
                        'names'    => ['zh-CN' => '翻译研究', 'en' => 'Translation studies'],
                    ],
                    [
                        'slug'     => 'reception-studies',
                        'names'    => ['zh-CN' => '接受研究', 'en' => 'Reception studies'],
                    ]
                ],
            ],
            [
                'slug'     => 'literary-theory',
                'names'    => ['zh-CN' => '文学理论', 'en' => 'Literary theory', 'ja' => '文学理論', 'ko' => '문학이론', 'fr' => 'Théorie littéraire', 'de' => 'Literaturtheorie'],
                'children' => [
                    [
                        'slug'     => 'narratology',
                        'names'    => ['zh-CN' => '叙事学', 'en' => 'Narratology'],
                    ],
                    [
                        'slug'     => 'literary-criticism',
                        'names'    => ['zh-CN' => '文学批评', 'en' => 'Literary criticism'],
                    ]
                ],
            ],
            [
                'slug'     => 'modern-and-contemporary-literature',
                'names'    => ['zh-CN' => '现当代文学', 'en' => 'Modern and contemporary literature', 'ja' => '近現代文学', 'ko' => '현대문학', 'fr' => 'Littérature moderne et contemporaine', 'de' => 'Moderne und Gegenwartsliteratur'],
                'children' => [
                    [
                        'slug'     => 'modernism',
                        'names'    => ['zh-CN' => '现代主义文学', 'en' => 'Modernism'],
                    ]
                ],
            ],
            [
                'slug'     => 'poetry',
                'names'    => ['zh-CN' => '诗歌研究', 'en' => 'Poetry', 'ja' => '詩学', 'ko' => '시학', 'fr' => 'Poésie', 'de' => 'Lyrikforschung'],
            ],
            [
                'slug'     => 'drama',
                'names'    => ['zh-CN' => '戏剧研究', 'en' => 'Drama', 'ja' => '演劇学', 'ko' => '희곡연구', 'fr' => 'Études théâtrales', 'de' => 'Dramenwissenschaft'],
            ],
            [
                'slug'     => 'childrens-literature',
                'names'    => ['zh-CN' => '儿童文学', 'en' => 'Children’s literature', 'ja' => '児童文学', 'ko' => '아동문학', 'fr' => 'Littérature jeunesse', 'de' => 'Kinder- und Jugendliteratur'],
            ]
        ],
    ],
    [
        'slug'     => 'political-science',
        'names'    => ['zh-CN' => '政治学', 'en' => 'Political science', 'ja' => '政治学', 'ko' => '정치학', 'fr' => 'Science politique', 'de' => 'Politikwissenschaft'],
        'children' => [
            [
                'slug'     => 'political-theory',
                'names'    => ['zh-CN' => '政治理论', 'en' => 'Political theory', 'ja' => '政治理論', 'ko' => '정치이론', 'fr' => 'Théorie politique', 'de' => 'Politische Theorie'],
                'children' => [
                    [
                        'slug'     => 'contemporary-political-theory',
                        'names'    => ['zh-CN' => '当代政治理论', 'en' => 'Contemporary political theory'],
                    ]
                ],
            ],
            [
                'slug'     => 'public-administration',
                'names'    => ['zh-CN' => '公共行政', 'en' => 'Public administration', 'ja' => '行政学', 'ko' => '행정학', 'fr' => 'Administration publique', 'de' => 'Verwaltungswissenschaft'],
                'children' => [
                    [
                        'slug'     => 'public-policy',
                        'names'    => ['zh-CN' => '公共政策', 'en' => 'Public policy'],
                    ]
                ],
            ],
            [
                'slug'     => 'international-relations',
                'names'    => ['zh-CN' => '国际关系', 'en' => 'International relations', 'ja' => '国際関係論', 'ko' => '국제관계', 'fr' => 'Relations internationales', 'de' => 'Internationale Beziehungen'],
                'children' => [
                    [
                        'slug'     => 'international-security',
                        'names'    => ['zh-CN' => '国际安全', 'en' => 'International security'],
                    ],
                    [
                        'slug'     => 'international-political-economy',
                        'names'    => ['zh-CN' => '国际政治经济学', 'en' => 'International political economy'],
                    ],
                    [
                        'slug'     => 'diplomatic-studies',
                        'names'    => ['zh-CN' => '外交学研究', 'en' => 'Diplomatic studies'],
                    ]
                ],
            ],
            [
                'slug'     => 'political-methodology',
                'names'    => ['zh-CN' => '政治学方法论', 'en' => 'Political methodology', 'ja' => '政治学方法論', 'ko' => '정치학 방법론', 'fr' => 'Méthodologie politique', 'de' => 'Politische Methodenlehre'],
            ],
            [
                'slug'     => 'political-institutions',
                'names'    => ['zh-CN' => '政治制度', 'en' => 'Political institutions', 'ja' => '政治制度', 'ko' => '정치제도', 'fr' => 'Institutions politiques', 'de' => 'Politische Institutionen'],
                'children' => [
                    [
                        'slug'     => 'legislative-studies',
                        'names'    => ['zh-CN' => '立法研究', 'en' => 'Legislative studies'],
                    ]
                ],
            ],
            [
                'slug'     => 'area-studies',
                'names'    => ['zh-CN' => '区域研究', 'en' => 'Area studies', 'ja' => '地域研究', 'ko' => '지역연구', 'fr' => 'Études régionales', 'de' => 'Regionalstudien'],
                'children' => [
                    [
                        'slug'     => 'east-asian-studies',
                        'names'    => ['zh-CN' => '东亚研究', 'en' => 'East Asian studies'],
                    ],
                    [
                        'slug'     => 'latin-american-studies',
                        'names'    => ['zh-CN' => '拉美研究', 'en' => 'Latin American studies'],
                    ]
                ],
            ]
        ],
    ],
    [
        'slug'     => 'sociology',
        'names'    => ['zh-CN' => '社会学', 'en' => 'Sociology', 'ja' => '社会学', 'ko' => '사회학', 'fr' => 'Sociologie', 'de' => 'Soziologie'],
        'children' => [
            [
                'slug'     => 'social-theory',
                'names'    => ['zh-CN' => '社会理论', 'en' => 'Social theory', 'ja' => '社会理論', 'ko' => '사회이론', 'fr' => 'Théorie sociale', 'de' => 'Sozialtheorie'],
                'children' => [
                    [
                        'slug'     => 'classical-social-theory',
                        'names'    => ['zh-CN' => '古典社会理论', 'en' => 'Classical social theory'],
                    ]
                ],
            ],
            [
                'slug'     => 'social-stratification',
                'names'    => ['zh-CN' => '社会分层', 'en' => 'Social stratification', 'ja' => '社会階層論', 'ko' => '사회계층론', 'fr' => 'Stratification sociale', 'de' => 'Soziale Ungleichheit'],
                'children' => [
                    [
                        'slug'     => 'class-and-mobility',
                        'names'    => ['zh-CN' => '阶级与流动', 'en' => 'Class and mobility'],
                    ]
                ],
            ],
            [
                'slug'     => 'urban-sociology',
                'names'    => ['zh-CN' => '城市社会学', 'en' => 'Urban sociology', 'ja' => '都市社会学', 'ko' => '도시사회학', 'fr' => 'Sociologie urbaine', 'de' => 'Stadtsoziologie'],
            ],
            [
                'slug'     => 'rural-sociology',
                'names'    => ['zh-CN' => '农村社会学', 'en' => 'Rural sociology', 'ja' => '農村社会学', 'ko' => '농촌사회학', 'fr' => 'Sociologie rurale', 'de' => 'Agrarsoziologie'],
            ],
            [
                'slug'     => 'family-sociology',
                'names'    => ['zh-CN' => '家庭社会学', 'en' => 'Family sociology', 'ja' => '家族社会学', 'ko' => '가족사회학', 'fr' => 'Sociologie de la famille', 'de' => 'Familiensoziologie'],
            ],
            [
                'slug'     => 'deviance-and-social-control',
                'names'    => ['zh-CN' => '越轨与社会控制', 'en' => 'Deviance and social control', 'ja' => '逸脱と社会統制', 'ko' => '일탈과 사회통제', 'fr' => 'Déviance et contrôle social', 'de' => 'Abweichung und soziale Kontrolle'],
                'children' => [
                    [
                        'slug'     => 'criminology',
                        'names'    => ['zh-CN' => '犯罪学', 'en' => 'Criminology'],
                    ],
                    [
                        'slug'     => 'penology',
                        'names'    => ['zh-CN' => '刑罚学', 'en' => 'Penology'],
                    ]
                ],
            ],
            [
                'slug'     => 'political-sociology',
                'names'    => ['zh-CN' => '政治社会学', 'en' => 'Political sociology', 'ja' => '政治社会学', 'ko' => '정치사회학', 'fr' => 'Sociologie politique', 'de' => 'Politische Soziologie'],
            ],
            [
                'slug'     => 'economic-sociology',
                'names'    => ['zh-CN' => '经济社会学', 'en' => 'Economic sociology', 'ja' => '経済社会学', 'ko' => '경제사회학', 'fr' => 'Sociologie économique', 'de' => 'Wirtschaftssoziologie'],
            ],
            [
                'slug'     => 'sociology-of-culture',
                'names'    => ['zh-CN' => '文化社会学', 'en' => 'Sociology of culture', 'ja' => '文化社会学', 'ko' => '문화사회학', 'fr' => 'Sociologie de la culture', 'de' => 'Kultursoziologie'],
            ],
            [
                'slug'     => 'gender-studies',
                'names'    => ['zh-CN' => '性别研究', 'en' => 'Gender studies', 'ja' => 'ジェンダー研究', 'ko' => '젠더 연구', 'fr' => 'Études de genre', 'de' => 'Geschlechterforschung'],
                'children' => [
                    [
                        'slug'     => 'feminist-theory',
                        'names'    => ['zh-CN' => '女性主义理论', 'en' => 'Feminist theory'],
                    ]
                ],
            ],
            [
                'slug'     => 'demography',
                'names'    => ['zh-CN' => '人口学', 'en' => 'Demography', 'ja' => '人口学', 'ko' => '인구학', 'fr' => 'Démographie', 'de' => 'Demographie'],
                'children' => [
                    [
                        'slug'     => 'population-aging',
                        'names'    => ['zh-CN' => '人口老龄化', 'en' => 'Population aging'],
                    ],
                    [
                        'slug'     => 'family-demography',
                        'names'    => ['zh-CN' => '家庭人口学', 'en' => 'Family demography'],
                    ]
                ],
            ]
        ],
    ],
    [
        'slug'     => 'anthropology',
        'names'    => ['zh-CN' => '人类学', 'en' => 'Anthropology', 'ja' => '人類学', 'ko' => '인류학', 'fr' => 'Anthropologie', 'de' => 'Anthropologie'],
        'children' => [
            [
                'slug'     => 'cultural-anthropology',
                'names'    => ['zh-CN' => '文化人类学', 'en' => 'Cultural anthropology', 'ja' => '文化人類学', 'ko' => '문화인류학', 'fr' => 'Anthropologie culturelle', 'de' => 'Kulturanthropologie'],
                'children' => [
                    [
                        'slug'     => 'ethnography',
                        'names'    => ['zh-CN' => '民族志', 'en' => 'Ethnography'],
                    ]
                ],
            ],
            [
                'slug'     => 'archaeology',
                'names'    => ['zh-CN' => '考古学', 'en' => 'Archaeology', 'ja' => '考古学', 'ko' => '고고학', 'fr' => 'Archéologie', 'de' => 'Archäologie'],
                'children' => [
                    [
                        'slug'     => 'prehistoric-archaeology',
                        'names'    => ['zh-CN' => '史前考古学', 'en' => 'Prehistoric archaeology'],
                    ]
                ],
            ],
            [
                'slug'     => 'biological-anthropology',
                'names'    => ['zh-CN' => '体质人类学', 'en' => 'Biological anthropology', 'ja' => '自然人類学', 'ko' => '생물인류학', 'fr' => 'Anthropologie biologique', 'de' => 'Bioanthropologie'],
                'children' => [
                    [
                        'slug'     => 'human-evolution',
                        'names'    => ['zh-CN' => '人类演化', 'en' => 'Human evolution'],
                    ]
                ],
            ],
            [
                'slug'     => 'applied-anthropology',
                'names'    => ['zh-CN' => '应用人类学', 'en' => 'Applied anthropology', 'ja' => '応用人類学', 'ko' => '응용인류학', 'fr' => 'Anthropologie appliquée', 'de' => 'Angewandte Anthropologie'],
            ],
            [
                'slug'     => 'ethnology',
                'names'    => ['zh-CN' => '民族学', 'en' => 'Ethnology', 'ja' => '民族学', 'ko' => '민족학', 'fr' => 'Ethnologie', 'de' => 'Ethnologie'],
                'children' => [
                    [
                        'slug'     => 'ethnicity-and-identity',
                        'names'    => ['zh-CN' => '族群与认同', 'en' => 'Ethnicity and identity'],
                    ]
                ],
            ]
        ],
    ],
    [
        'slug'     => 'art',
        'names'    => ['zh-CN' => '艺术学', 'en' => 'Art', 'ja' => '芸術学', 'ko' => '예술학', 'fr' => 'Arts', 'de' => 'Kunstwissenschaft'],
        'children' => [
            [
                'slug'     => 'art-history-periods',
                'names'    => ['zh-CN' => '美术史断代', 'en' => 'Art history by period', 'ja' => '美術史（時代別）', 'ko' => '미술사(시대별)', 'fr' => 'Histoire de l’art par période', 'de' => 'Kunstgeschichte nach Epochen'],
                'children' => [
                    [
                        'slug'     => 'chinese-art-history',
                        'names'    => ['zh-CN' => '中国美术史', 'en' => 'Chinese art history'],
                    ],
                    [
                        'slug'     => 'western-art-history',
                        'names'    => ['zh-CN' => '西方美术史', 'en' => 'Western art history'],
                    ]
                ],
            ],
            [
                'slug'     => 'art-theory',
                'names'    => ['zh-CN' => '艺术理论', 'en' => 'Art theory', 'ja' => '芸術理論', 'ko' => '예술이론', 'fr' => 'Théorie de l’art', 'de' => 'Kunsttheorie'],
                'children' => [
                    [
                        'slug'     => 'visual-culture',
                        'names'    => ['zh-CN' => '视觉文化', 'en' => 'Visual culture'],
                    ]
                ],
            ],
            [
                'slug'     => 'oil-painting',
                'names'    => ['zh-CN' => '油画', 'en' => 'Oil painting', 'ja' => '油彩画', 'ko' => '유화', 'fr' => 'Peinture à l’huile', 'de' => 'Ölmalerei'],
            ],
            [
                'slug'     => 'chinese-painting',
                'names'    => ['zh-CN' => '中国画', 'en' => 'Chinese painting', 'ja' => '中国画', 'ko' => '중국화', 'fr' => 'Peinture chinoise', 'de' => 'Chinesische Malerei'],
                'children' => [
                    [
                        'slug'     => 'ink-painting',
                        'names'    => ['zh-CN' => '水墨画', 'en' => 'Ink painting'],
                    ]
                ],
            ],
            [
                'slug'     => 'design',
                'names'    => ['zh-CN' => '设计学', 'en' => 'Design', 'ja' => 'デザイン学', 'ko' => '디자인학', 'fr' => 'Design', 'de' => 'Design'],
                'children' => [
                    [
                        'slug'     => 'visual-communication-design',
                        'names'    => ['zh-CN' => '视觉传达设计', 'en' => 'Visual communication design'],
                    ],
                    [
                        'slug'     => 'industrial-design',
                        'names'    => ['zh-CN' => '工业设计', 'en' => 'Industrial design'],
                    ],
                    [
                        'slug'     => 'environmental-design',
                        'names'    => ['zh-CN' => '环境设计', 'en' => 'Environmental design'],
                    ]
                ],
            ],
            [
                'slug'     => 'musicology',
                'names'    => ['zh-CN' => '音乐学', 'en' => 'Musicology', 'ja' => '音楽学', 'ko' => '음악학', 'fr' => 'Musicologie', 'de' => 'Musikwissenschaft'],
                'children' => [
                    [
                        'slug'     => 'music-history',
                        'names'    => ['zh-CN' => '音乐史', 'en' => 'Music history'],
                    ]
                ],
            ],
            [
                'slug'     => 'music-performance',
                'names'    => ['zh-CN' => '音乐表演', 'en' => 'Music performance', 'ja' => '音楽演奏', 'ko' => '음악연주', 'fr' => 'Interprétation musicale', 'de' => 'Musikalische Aufführungspraxis'],
                'children' => [
                    [
                        'slug'     => 'instrumental-performance',
                        'names'    => ['zh-CN' => '器乐表演', 'en' => 'Instrumental performance'],
                    ]
                ],
            ],
            [
                'slug'     => 'film-and-moving-image',
                'names'    => ['zh-CN' => '电影与动态影像', 'en' => 'Film and moving image', 'ja' => '映画学', 'ko' => '영화학', 'fr' => 'Études cinématographiques', 'de' => 'Filmwissenschaft'],
                'children' => [
                    [
                        'slug'     => 'film-theory',
                        'names'    => ['zh-CN' => '电影理论', 'en' => 'Film theory'],
                    ],
                    [
                        'slug'     => 'documentary-studies',
                        'names'    => ['zh-CN' => '纪录片研究', 'en' => 'Documentary studies'],
                    ]
                ],
            ]
        ],
    ],
    [
        'slug'     => 'media-studies',
        'names'    => ['zh-CN' => '新闻传播学', 'en' => 'Media studies', 'ja' => 'メディア研究', 'ko' => '미디어학', 'fr' => 'Sciences de l’information et de la communication', 'de' => 'Medienwissenschaft'],
        'children' => [
            [
                'slug'     => 'journalism',
                'names'    => ['zh-CN' => '新闻学', 'en' => 'Journalism', 'ja' => 'ジャーナリズム', 'ko' => '저널리즘', 'fr' => 'Journalisme', 'de' => 'Journalistik'],
                'children' => [
                    [
                        'slug'     => 'data-journalism',
                        'names'    => ['zh-CN' => '数据新闻', 'en' => 'Data journalism'],
                    ]
                ],
            ],
            [
                'slug'     => 'communication-studies',
                'names'    => ['zh-CN' => '传播学', 'en' => 'Communication studies', 'ja' => 'コミュニケーション学', 'ko' => '커뮤니케이션학', 'fr' => 'Sciences de la communication', 'de' => 'Kommunikationswissenschaft'],
                'children' => [
                    [
                        'slug'     => 'media-effects',
                        'names'    => ['zh-CN' => '媒介效果', 'en' => 'Media effects'],
                    ],
                    [
                        'slug'     => 'intercultural-communication',
                        'names'    => ['zh-CN' => '跨文化传播', 'en' => 'Intercultural communication'],
                    ]
                ],
            ],
            [
                'slug'     => 'media-and-politics',
                'names'    => ['zh-CN' => '政治传播', 'en' => 'Media and politics', 'ja' => '政治コミュニケーション', 'ko' => '정치커뮤니케이션', 'fr' => 'Communication politique', 'de' => 'Politische Kommunikation'],
                'children' => [
                    [
                        'slug'     => 'public-opinion',
                        'names'    => ['zh-CN' => '舆论研究', 'en' => 'Public opinion'],
                    ]
                ],
            ],
            [
                'slug'     => 'digital-media',
                'names'    => ['zh-CN' => '数字媒体', 'en' => 'Digital media', 'ja' => 'デジタルメディア', 'ko' => '디지털미디어', 'fr' => 'Médias numériques', 'de' => 'Digitale Medien'],
            ],
            [
                'slug'     => 'advertising-and-public-relations',
                'names'    => ['zh-CN' => '广告与公共关系', 'en' => 'Advertising and public relations', 'ja' => '広告・広報', 'ko' => '광고 및 홍보', 'fr' => 'Publicité et relations publiques', 'de' => 'Werbung und Public Relations'],
            ],
            [
                'slug'     => 'publishing-studies',
                'names'    => ['zh-CN' => '出版研究', 'en' => 'Publishing studies', 'ja' => '出版学', 'ko' => '출판학', 'fr' => 'Études de l’édition', 'de' => 'Verlagswissenschaft'],
            ],
            [
                'slug'     => 'broadcast-media',
                'names'    => ['zh-CN' => '广播电视学', 'en' => 'Broadcast media', 'ja' => '放送メディア', 'ko' => '방송미디어', 'fr' => 'Médias audiovisuels', 'de' => 'Rundfunkmedien'],
                'children' => [
                    [
                        'slug'     => 'television-studies',
                        'names'    => ['zh-CN' => '电视研究', 'en' => 'Television studies'],
                    ]
                ],
            ],
            [
                'slug'     => 'media-literacy',
                'names'    => ['zh-CN' => '媒介素养', 'en' => 'Media literacy', 'ja' => 'メディアリテラシー', 'ko' => '미디어 리터러시', 'fr' => 'Éducation aux médias', 'de' => 'Medienkompetenz'],
            ]
        ],
    ],
    [
        'slug'     => 'law',
        'names'    => ['zh-CN' => '法学', 'en' => 'Law', 'ja' => '法学', 'ko' => '법학', 'fr' => 'Droit', 'de' => 'Rechtswissenschaft'],
        'children' => [
            [
                'slug'     => 'jurisprudence',
                'names'    => ['zh-CN' => '法理学', 'en' => 'Jurisprudence', 'ja' => '法哲学', 'ko' => '법철학', 'fr' => 'Théorie du droit', 'de' => 'Rechtstheorie'],
                'children' => [
                    [
                        'slug'     => 'legal-dogmatics',
                        'names'    => ['zh-CN' => '法教义学', 'en' => 'Legal dogmatics'],
                    ],
                    [
                        'slug'     => 'comparative-law',
                        'names'    => ['zh-CN' => '比较法', 'en' => 'Comparative law'],
                    ]
                ],
            ],
            [
                'slug'     => 'civil-law',
                'names'    => ['zh-CN' => '民法学', 'en' => 'Civil law', 'ja' => '民法', 'ko' => '민법', 'fr' => 'Droit civil', 'de' => 'Zivilrecht'],
                'children' => [
                    [
                        'slug'     => 'contract-law',
                        'names'    => ['zh-CN' => '合同法', 'en' => 'Contract law'],
                    ],
                    [
                        'slug'     => 'property-law',
                        'names'    => ['zh-CN' => '物权法', 'en' => 'Property law'],
                    ],
                    [
                        'slug'     => 'tort-law',
                        'names'    => ['zh-CN' => '侵权法', 'en' => 'Tort law'],
                    ]
                ],
            ],
            [
                'slug'     => 'criminal-law',
                'names'    => ['zh-CN' => '刑法学', 'en' => 'Criminal law', 'ja' => '刑法', 'ko' => '형법', 'fr' => 'Droit pénal', 'de' => 'Strafrecht'],
                'children' => [
                    [
                        'slug'     => 'criminology-and-criminal-justice',
                        'names'    => ['zh-CN' => '犯罪学与刑事司法', 'en' => 'Criminology and criminal justice'],
                    ]
                ],
            ],
            [
                'slug'     => 'procedural-law',
                'names'    => ['zh-CN' => '诉讼法学', 'en' => 'Procedural law', 'ja' => '訴訟法', 'ko' => '소송법', 'fr' => 'Droit processuel', 'de' => 'Prozessrecht'],
                'children' => [
                    [
                        'slug'     => 'civil-procedure',
                        'names'    => ['zh-CN' => '民事诉讼法', 'en' => 'Civil procedure'],
                    ],
                    [
                        'slug'     => 'criminal-procedure',
                        'names'    => ['zh-CN' => '刑事诉讼法', 'en' => 'Criminal procedure'],
                    ]
                ],
            ],
            [
                'slug'     => 'administrative-law',
                'names'    => ['zh-CN' => '行政法学', 'en' => 'Administrative law', 'ja' => '行政法', 'ko' => '행정법', 'fr' => 'Droit administratif', 'de' => 'Verwaltungsrecht'],
            ],
            [
                'slug'     => 'economic-law',
                'names'    => ['zh-CN' => '经济法', 'en' => 'Economic law', 'ja' => '経済法', 'ko' => '경제법', 'fr' => 'Droit économique', 'de' => 'Wirtschaftsrecht'],
                'children' => [
                    [
                        'slug'     => 'competition-law',
                        'names'    => ['zh-CN' => '竞争法', 'en' => 'Competition law'],
                    ]
                ],
            ],
            [
                'slug'     => 'international-law',
                'names'    => ['zh-CN' => '国际法学', 'en' => 'International law', 'ja' => '国際法', 'ko' => '국제법', 'fr' => 'Droit international', 'de' => 'Völkerrecht'],
                'children' => [
                    [
                        'slug'     => 'private-international-law',
                        'names'    => ['zh-CN' => '国际私法', 'en' => 'Private international law'],
                    ]
                ],
            ],
            [
                'slug'     => 'intellectual-property-law',
                'names'    => ['zh-CN' => '知识产权法', 'en' => 'Intellectual property law', 'ja' => '知的財産法', 'ko' => '지식재산권법', 'fr' => 'Droit de la propriété intellectuelle', 'de' => 'Immaterialgüterrecht'],
                'children' => [
                    [
                        'slug'     => 'patent-law',
                        'names'    => ['zh-CN' => '专利法', 'en' => 'Patent law'],
                    ],
                    [
                        'slug'     => 'copyright-law',
                        'names'    => ['zh-CN' => '著作权法', 'en' => 'Copyright law'],
                    ]
                ],
            ]
        ],
    ],
    [
        'slug'     => 'education',
        'names'    => ['zh-CN' => '教育学', 'en' => 'Education', 'ja' => '教育学', 'ko' => '교육학', 'fr' => 'Sciences de l’éducation', 'de' => 'Erziehungswissenschaft'],
        'children' => [
            [
                'slug'     => 'curriculum-and-instruction',
                'names'    => ['zh-CN' => '课程与教学论', 'en' => 'Curriculum and instruction', 'ja' => 'カリキュラムと教授法', 'ko' => '교육과정 및 수업', 'fr' => 'Curriculum et didactique', 'de' => 'Curriculum und Didaktik'],
                'children' => [
                    [
                        'slug'     => 'instructional-design',
                        'names'    => ['zh-CN' => '教学设计', 'en' => 'Instructional design'],
                    ],
                    [
                        'slug'     => 'subject-didactics',
                        'names'    => ['zh-CN' => '学科教学法', 'en' => 'Subject didactics'],
                    ]
                ],
            ],
            [
                'slug'     => 'philosophy-of-education',
                'names'    => ['zh-CN' => '教育哲学', 'en' => 'Philosophy of education', 'ja' => '教育哲学', 'ko' => '교육철학', 'fr' => 'Philosophie de l’éducation', 'de' => 'Bildungsphilosophie'],
            ],
            [
                'slug'     => 'higher-education',
                'names'    => ['zh-CN' => '高等教育学', 'en' => 'Higher education', 'ja' => '高等教育学', 'ko' => '고등교육학', 'fr' => 'Enseignement supérieur', 'de' => 'Hochschulforschung'],
            ],
            [
                'slug'     => 'teacher-education',
                'names'    => ['zh-CN' => '教师教育', 'en' => 'Teacher education', 'ja' => '教員養成', 'ko' => '교원교육', 'fr' => 'Formation des enseignants', 'de' => 'Lehrerbildung'],
            ],
            [
                'slug'     => 'educational-technology',
                'names'    => ['zh-CN' => '教育技术学', 'en' => 'Educational technology', 'ja' => '教育工学', 'ko' => '교육공학', 'fr' => 'Technologies éducatives', 'de' => 'Mediendidaktik'],
                'children' => [
                    [
                        'slug'     => 'online-learning',
                        'names'    => ['zh-CN' => '在线学习', 'en' => 'Online learning'],
                    ],
                    [
                        'slug'     => 'blended-learning',
                        'names'    => ['zh-CN' => '混合式学习', 'en' => 'Blended learning'],
                    ],
                    [
                        'slug'     => 'learning-analytics',
                        'names'    => ['zh-CN' => '学习分析', 'en' => 'Learning analytics'],
                    ]
                ],
            ],
            [
                'slug'     => 'comparative-and-international-education',
                'names'    => ['zh-CN' => '比较教育学', 'en' => 'Comparative and international education', 'ja' => '比較教育学', 'ko' => '비교교육학', 'fr' => 'Éducation comparée', 'de' => 'Vergleichende Erziehungswissenschaft'],
            ],
            [
                'slug'     => 'early-childhood-education',
                'names'    => ['zh-CN' => '学前教育学', 'en' => 'Early childhood education', 'ja' => '幼児教育学', 'ko' => '유아교육학', 'fr' => 'Éducation préscolaire', 'de' => 'Elementarpädagogik'],
            ],
            [
                'slug'     => 'special-education',
                'names'    => ['zh-CN' => '特殊教育学', 'en' => 'Special education', 'ja' => '特別支援教育', 'ko' => '특수교육학', 'fr' => 'Éducation spécialisée', 'de' => 'Sonderpädagogik'],
                'children' => [
                    [
                        'slug'     => 'inclusive-education',
                        'names'    => ['zh-CN' => '融合教育', 'en' => 'Inclusive education'],
                    ]
                ],
            ],
            [
                'slug'     => 'adult-and-lifelong-education',
                'names'    => ['zh-CN' => '成人教育与终身学习', 'en' => 'Adult and lifelong education', 'ja' => '生涯学習', 'ko' => '성인 및 평생교육', 'fr' => 'Éducation des adultes', 'de' => 'Erwachsenen- und Weiterbildung'],
            ]
        ],
    ],
    [
        'slug'     => 'statistics',
        'names'    => ['zh-CN' => '统计学', 'en' => 'Statistics', 'ja' => '統計学', 'ko' => '통계학', 'fr' => 'Statistique', 'de' => 'Statistik'],
        'children' => [
            [
                'slug'     => 'mathematical-statistics',
                'names'    => ['zh-CN' => '数理统计', 'en' => 'Mathematical statistics', 'ja' => '数理統計学', 'ko' => '수리통계학', 'fr' => 'Statistique mathématique', 'de' => 'Mathematische Statistik'],
                'children' => [
                    [
                        'slug'     => 'estimation-theory',
                        'names'    => ['zh-CN' => '估计理论', 'en' => 'Estimation theory'],
                    ],
                    [
                        'slug'     => 'hypothesis-testing',
                        'names'    => ['zh-CN' => '假设检验', 'en' => 'Hypothesis testing'],
                    ]
                ],
            ],
            [
                'slug'     => 'applied-statistics',
                'names'    => ['zh-CN' => '应用统计', 'en' => 'Applied statistics', 'ja' => '応用統計学', 'ko' => '응용통계학', 'fr' => 'Statistique appliquée', 'de' => 'Angewandte Statistik'],
                'children' => [
                    [
                        'slug'     => 'regression-analysis',
                        'names'    => ['zh-CN' => '回归分析', 'en' => 'Regression analysis'],
                    ],
                    [
                        'slug'     => 'nonparametric-statistics',
                        'names'    => ['zh-CN' => '非参数统计', 'en' => 'Nonparametric statistics'],
                    ]
                ],
            ],
            [
                'slug'     => 'bayesian-statistics',
                'names'    => ['zh-CN' => '贝叶斯统计', 'en' => 'Bayesian statistics', 'ja' => 'ベイズ統計学', 'ko' => '베이즈 통계학', 'fr' => 'Statistique bayésienne', 'de' => 'Bayessche Statistik'],
                'children' => [
                    [
                        'slug'     => 'bayesian-inference',
                        'names'    => ['zh-CN' => '贝叶斯推断', 'en' => 'Bayesian inference'],
                    ]
                ],
            ],
            [
                'slug'     => 'computational-statistics',
                'names'    => ['zh-CN' => '计算统计', 'en' => 'Computational statistics', 'ja' => '計算統計学', 'ko' => '계산통계학', 'fr' => 'Statistique computationnelle', 'de' => 'Computational Statistics'],
            ],
            [
                'slug'     => 'biostatistics-and-epidemiology',
                'names'    => ['zh-CN' => '生物统计与流行病学统计', 'en' => 'Biostatistics and epidemiological statistics', 'ja' => '生物統計学', 'ko' => '생물통계학', 'fr' => 'Biostatistique', 'de' => 'Biostatistik'],
                'children' => [
                    [
                        'slug'     => 'clinical-trials',
                        'names'    => ['zh-CN' => '临床试验设计', 'en' => 'Clinical trials'],
                    ]
                ],
            ],
            [
                'slug'     => 'time-series-and-forecasting',
                'names'    => ['zh-CN' => '时间序列与预测', 'en' => 'Time series and forecasting', 'ja' => '時系列解析', 'ko' => '시계열분석', 'fr' => 'Séries temporelles', 'de' => 'Zeitreihenanalyse'],
            ]
        ],
    ],
    [
        'slug'     => 'agriculture',
        'names'    => ['zh-CN' => '农学', 'en' => 'Agriculture', 'ja' => '農学', 'ko' => '농학', 'fr' => 'Agronomie', 'de' => 'Agrarwissenschaften'],
        'children' => [
            [
                'slug'     => 'crop-science',
                'names'    => ['zh-CN' => '作物学', 'en' => 'Crop science', 'ja' => '作物学', 'ko' => '작물학', 'fr' => 'Sciences des cultures', 'de' => 'Pflanzenbau'],
                'children' => [
                    [
                        'slug'     => 'plant-breeding',
                        'names'    => ['zh-CN' => '植物育种', 'en' => 'Plant breeding'],
                    ],
                    [
                        'slug'     => 'crop-cultivation',
                        'names'    => ['zh-CN' => '作物栽培学', 'en' => 'Crop cultivation'],
                    ]
                ],
            ],
            [
                'slug'     => 'veterinary-medicine',
                'names'    => ['zh-CN' => '兽医学', 'en' => 'Veterinary medicine', 'ja' => '獣医学', 'ko' => '수의학', 'fr' => 'Médecine vétérinaire', 'de' => 'Veterinärmedizin'],
            ],
            [
                'slug'     => 'agricultural-economics',
                'names'    => ['zh-CN' => '农业经济学', 'en' => 'Agricultural economics', 'ja' => '農業経済学', 'ko' => '농업경제학', 'fr' => 'Économie agricole', 'de' => 'Agrarökonomie'],
            ],
            [
                'slug'     => 'horticulture',
                'names'    => ['zh-CN' => '园艺学', 'en' => 'Horticulture', 'ja' => '園芸学', 'ko' => '원예학', 'fr' => 'Horticulture', 'de' => 'Gartenbau'],
                'children' => [
                    [
                        'slug'     => 'pomology',
                        'names'    => ['zh-CN' => '果树学', 'en' => 'Pomology'],
                    ]
                ],
            ],
            [
                'slug'     => 'forestry',
                'names'    => ['zh-CN' => '林学', 'en' => 'Forestry', 'ja' => '林学', 'ko' => '임학', 'fr' => 'Sylviculture', 'de' => 'Forstwissenschaft'],
                'children' => [
                    [
                        'slug'     => 'silviculture',
                        'names'    => ['zh-CN' => '森林培育学', 'en' => 'Silviculture'],
                    ]
                ],
            ],
            [
                'slug'     => 'soil-and-water-engineering',
                'names'    => ['zh-CN' => '农业水土工程', 'en' => 'Soil and water engineering in agriculture', 'ja' => '農業水利工学', 'ko' => '농업수자원공학', 'fr' => 'Génie rural', 'de' => 'Agrartechnik und Wasserwirtschaft'],
            ],
            [
                'slug'     => 'food-science',
                'names'    => ['zh-CN' => '食品科学', 'en' => 'Food science', 'ja' => '食品科学', 'ko' => '식품과학', 'fr' => 'Science des aliments', 'de' => 'Lebensmittelwissenschaft'],
            ],
            [
                'slug'     => 'aquaculture',
                'names'    => ['zh-CN' => '水产养殖', 'en' => 'Aquaculture', 'ja' => '水産養殖', 'ko' => '수산양식', 'fr' => 'Aquaculture', 'de' => 'Aquakultur'],
            ]
        ],
    ],
    [
        'slug'     => 'architecture',
        'names'    => ['zh-CN' => '建筑学', 'en' => 'Architecture', 'ja' => '建築学', 'ko' => '건축학', 'fr' => 'Architecture', 'de' => 'Architektur'],
        'children' => [
            [
                'slug'     => 'architectural-design',
                'names'    => ['zh-CN' => '建筑设计', 'en' => 'Architectural design', 'ja' => '建築設計', 'ko' => '건축설계', 'fr' => 'Conception architecturale', 'de' => 'Entwurfslehre'],
                'children' => [
                    [
                        'slug'     => 'architectural-composition',
                        'names'    => ['zh-CN' => '建筑构图', 'en' => 'Architectural composition'],
                    ],
                    [
                        'slug'     => 'building-information-modeling',
                        'names'    => ['zh-CN' => '建筑信息模型', 'en' => 'Building information modeling'],
                    ],
                    [
                        'slug'     => 'housing-design',
                        'names'    => ['zh-CN' => '居住建筑设计', 'en' => 'Housing design'],
                    ]
                ],
            ],
            [
                'slug'     => 'architectural-history',
                'names'    => ['zh-CN' => '建筑史', 'en' => 'Architectural history', 'ja' => '建築史', 'ko' => '건축사', 'fr' => 'Histoire de l’architecture', 'de' => 'Architekturgeschichte'],
                'children' => [
                    [
                        'slug'     => 'historic-preservation',
                        'names'    => ['zh-CN' => '建筑遗产保护', 'en' => 'Historic preservation'],
                    ]
                ],
            ],
            [
                'slug'     => 'urban-planning',
                'names'    => ['zh-CN' => '城乡规划', 'en' => 'Urban planning', 'ja' => '都市計画', 'ko' => '도시계획', 'fr' => 'Urbanisme', 'de' => 'Stadtplanung'],
            ],
            [
                'slug'     => 'landscape-architecture',
                'names'    => ['zh-CN' => '风景园林', 'en' => 'Landscape architecture', 'ja' => 'ランドスケープ', 'ko' => '조경학', 'fr' => 'Architecture du paysage', 'de' => 'Landschaftsarchitektur'],
            ],
            [
                'slug'     => 'building-technology',
                'names'    => ['zh-CN' => '建筑技术科学', 'en' => 'Building technology', 'ja' => '建築工学', 'ko' => '건축기술', 'fr' => 'Technologie du bâtiment', 'de' => 'Bautechnik'],
            ]
        ],
    ],
    [
        'slug'     => 'environmental-science',
        'names'    => ['zh-CN' => '环境科学', 'en' => 'Environmental science', 'ja' => '環境科学', 'ko' => '환경과학', 'fr' => 'Sciences de l’environnement', 'de' => 'Umweltwissenschaften'],
        'children' => [
            [
                'slug'     => 'environmental-ecology',
                'names'    => ['zh-CN' => '环境生态学', 'en' => 'Environmental ecology', 'ja' => '環境生態学', 'ko' => '환경생태학', 'fr' => 'Écologie environnementale', 'de' => 'Umweltökologie'],
                'children' => [
                    [
                        'slug'     => 'biodiversity',
                        'names'    => ['zh-CN' => '生物多样性', 'en' => 'Biodiversity'],
                    ]
                ],
            ],
            [
                'slug'     => 'environmental-monitoring',
                'names'    => ['zh-CN' => '环境监测', 'en' => 'Environmental monitoring', 'ja' => '環境モニタリング', 'ko' => '환경모니터링', 'fr' => 'Surveillance environnementale', 'de' => 'Umweltmonitoring'],
                'children' => [
                    [
                        'slug'     => 'environmental-modeling',
                        'names'    => ['zh-CN' => '环境建模', 'en' => 'Environmental modeling'],
                    ],
                    [
                        'slug'     => 'environmental-informatics',
                        'names'    => ['zh-CN' => '环境信息学', 'en' => 'Environmental informatics'],
                    ]
                ],
            ],
            [
                'slug'     => 'pollution-science',
                'names'    => ['zh-CN' => '环境污染', 'en' => 'Pollution science', 'ja' => '環境汚染学', 'ko' => '환경오염', 'fr' => 'Science de la pollution', 'de' => 'Umweltverschmutzung'],
                'children' => [
                    [
                        'slug'     => 'air-quality',
                        'names'    => ['zh-CN' => '空气质量', 'en' => 'Air quality'],
                    ],
                    [
                        'slug'     => 'soil-contamination',
                        'names'    => ['zh-CN' => '土壤污染', 'en' => 'Soil contamination'],
                    ]
                ],
            ],
            [
                'slug'     => 'sustainability',
                'names'    => ['zh-CN' => '可持续发展', 'en' => 'Sustainability', 'ja' => '持続可能性', 'ko' => '지속가능성', 'fr' => 'Durabilité', 'de' => 'Nachhaltigkeit'],
                'children' => [
                    [
                        'slug'     => 'sustainable-development',
                        'names'    => ['zh-CN' => '可持续发展理论', 'en' => 'Sustainable development'],
                    ]
                ],
            ],
            [
                'slug'     => 'conservation-science',
                'names'    => ['zh-CN' => '保护科学', 'en' => 'Conservation science', 'ja' => '保全科学', 'ko' => '보전과학', 'fr' => 'Science de la conservation', 'de' => 'Naturschutzwissenschaft'],
            ],
            [
                'slug'     => 'natural-hazards',
                'names'    => ['zh-CN' => '自然灾害', 'en' => 'Natural hazards', 'ja' => '自然災害', 'ko' => '자연재해', 'fr' => 'Risques naturels', 'de' => 'Naturgefahren'],
            ]
        ],
    ],
    [
        'slug'     => 'climate-science',
        'names'    => ['zh-CN' => '气候科学', 'en' => 'Climate science', 'ja' => '気候科学', 'ko' => '기후과학', 'fr' => 'Science du climat', 'de' => 'Klimawissenschaft'],
        'children' => [
            [
                'slug'     => 'climate-change',
                'names'    => ['zh-CN' => '气候变化', 'en' => 'Climate change', 'ja' => '気候変動', 'ko' => '기후변화', 'fr' => 'Changement climatique', 'de' => 'Klimawandel'],
                'children' => [
                    [
                        'slug'     => 'global-warming',
                        'names'    => ['zh-CN' => '全球变暖', 'en' => 'Global warming'],
                    ],
                    [
                        'slug'     => 'climate-impacts',
                        'names'    => ['zh-CN' => '气候影响', 'en' => 'Climate impacts'],
                    ]
                ],
            ],
            [
                'slug'     => 'climatology',
                'names'    => ['zh-CN' => '气候学', 'en' => 'Climatology', 'ja' => '気候学', 'ko' => '기후학', 'fr' => 'Climatologie', 'de' => 'Klimatologie'],
            ],
            [
                'slug'     => 'climate-modeling',
                'names'    => ['zh-CN' => '气候模拟', 'en' => 'Climate modeling', 'ja' => '気候モデリング', 'ko' => '기후모델링', 'fr' => 'Modélisation du climat', 'de' => 'Klimamodellierung'],
            ],
            [
                'slug'     => 'climate-policy',
                'names'    => ['zh-CN' => '气候政策', 'en' => 'Climate policy', 'ja' => '気候政策', 'ko' => '기후정책', 'fr' => 'Politique climatique', 'de' => 'Klimapolitik'],
            ]
        ],
    ],];
