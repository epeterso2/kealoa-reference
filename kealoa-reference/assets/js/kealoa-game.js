/**
 * KEALOA Reference - Interactive Game
 *
 * Client-side game logic for the KEALOA Play Game block.
 * Loads round data via REST API, then runs entirely client-side.
 *
 * @package KEALOA_Reference
 */

(function () {
    'use strict';

    var containers = document.querySelectorAll('.kealoa-game');
    if (!containers.length) {
        return;
    }

    containers.forEach(function (container) { initGame(container); });

    function initGame(container) {

    var restUrl = container.getAttribute('data-rest-url');
    var nonce = container.getAttribute('data-nonce');
    var roundIds = JSON.parse(container.getAttribute('data-round-ids') || '[]');
    var forceRoundId = container.getAttribute('data-force-round') || null;

    if (!roundIds.length) {
        return;
    }

    // Game state
    var roundData = null;
    var currentClueIndex = 0;
    var userAnswers = [];
    var usedRoundIds = [];
    var shuffleMode = false;

    // =========================================================================
    // Helpers
    // =========================================================================

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (key) {
                if (key === 'className') {
                    node.className = attrs[key];
                } else if (key === 'textContent') {
                    node.textContent = attrs[key];
                } else if (key === 'innerHTML') {
                    node.innerHTML = attrs[key];
                } else if (key.indexOf('on') === 0) {
                    node.addEventListener(key.substring(2).toLowerCase(), attrs[key]);
                } else {
                    node.setAttribute(key, attrs[key]);
                }
            });
        }
        if (children) {
            children.forEach(function (child) {
                if (typeof child === 'string') {
                    node.appendChild(document.createTextNode(child));
                } else if (child) {
                    node.appendChild(child);
                }
            });
        }
        return node;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        var parts = dateStr.split('-');
        if (parts.length === 3) {
            return parseInt(parts[1], 10) + '/' + parseInt(parts[2], 10) + '/' + parts[0];
        }
        return dateStr;
    }

    function getDayOfWeek(dateStr) {
        if (!dateStr) return '';
        var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        var d = new Date(dateStr + 'T00:00:00');
        return days[d.getDay()] || '';
    }

    function shuffleArray(arr) {
        var a = arr.slice();
        for (var i = a.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var tmp = a[i];
            a[i] = a[j];
            a[j] = tmp;
        }
        return a;
    }

    function pickRandomRound() {
        // Prefer rounds not yet played this session
        var available = roundIds.filter(function (id) {
            return usedRoundIds.indexOf(id) === -1;
        });
        if (available.length === 0) {
            // All rounds played, reset
            usedRoundIds = [];
            available = roundIds.slice();
        }
        var idx = Math.floor(Math.random() * available.length);
        var chosen = available[idx];
        usedRoundIds.push(chosen);
        return chosen;
    }

    // =========================================================================
    // Screens
    // =========================================================================

    function showLoading() {
        container.innerHTML = '';
        container.appendChild(
            el('div', { className: 'kealoa-game__loading' }, [
                el('div', { className: 'kealoa-game__spinner' }),
                el('p', { textContent: 'Loading round data\u2026' })
            ])
        );
    }

    function showError(message) {
        container.innerHTML = '';
        container.appendChild(
            el('div', { className: 'kealoa-game__error' }, [
                el('p', { textContent: message }),
                el('div', { className: 'kealoa-game__mode-buttons' }, [
                    el('button', {
                        type: 'button',
                        className: 'kealoa-game__start-btn',
                        textContent: 'In Show Order',
                        onClick: function () { startGame('show'); }
                    }),
                    el('button', {
                        type: 'button',
                        className: 'kealoa-game__start-btn',
                        textContent: 'In Random Order',
                        onClick: function () { startGame('random'); }
                    })
                ])
            ])
        );
    }

    function startGame(mode) {
        if (typeof mode === 'string') {
            shuffleMode = mode === 'random';
        }
        showLoading();
        var roundId = forceRoundId ? parseInt(forceRoundId, 10) : pickRandomRound();
        fetch(restUrl + '/' + roundId, {
            headers: { 'X-WP-Nonce': nonce }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to load round data.');
                }
                return response.json();
            })
            .then(function (data) {
                roundData = data;
                if (shuffleMode) {
                    roundData.clues = shuffleArray(roundData.clues);
                }
                currentClueIndex = 0;
                userAnswers = [];
                showClue();
            })
            .catch(function (err) {
                showError(err.message || 'An error occurred loading the game.');
            });
    }

    function showClue() {
        var clue = roundData.clues[currentClueIndex];
        var clueNum = currentClueIndex + 1;
        var totalClues = roundData.clues.length;

        container.innerHTML = '';

        // Progress bar
        var progressPct = ((clueNum - 1) / totalClues * 100).toFixed(1);
        var progressBar = el('div', { className: 'kealoa-game__progress' }, [
            el('div', { className: 'kealoa-game__progress-bar', style: 'width:' + progressPct + '%' })
        ]);

        // Round info header
        var roundInfoChildren = [
            el('span', { className: 'kealoa-game__round-label', textContent: 'KEALOA #' + roundData.round_id }),
            el('span', { className: 'kealoa-game__clue-counter', textContent: 'Clue ' + clueNum + ' of ' + totalClues })
        ];
        var roundInfo = el('div', { className: 'kealoa-game__round-info' }, roundInfoChildren);

        // Round description and players (shown only on the first clue)
        var roundDetails = null;
        if (clueNum === 1) {
            var detailItems = [];
            if (roundData.description) {
                detailItems.push(el('p', { className: 'kealoa-game__round-description', textContent: roundData.description }));
            }
            if (roundData.players && roundData.players.length) {
                detailItems.push(el('p', { className: 'kealoa-game__round-players', innerHTML: '<strong>Players:</strong> ' + roundData.players.map(escapeHtml).join(', ') }));
            }
            if (roundData.clue_giver) {
                detailItems.push(el('p', { className: 'kealoa-game__round-clue-giver', innerHTML: '<strong>Clue Giver:</strong> ' + escapeHtml(roundData.clue_giver) }));
            }
            if (detailItems.length) {
                roundDetails = el('div', { className: 'kealoa-game__round-details' }, detailItems);
            }
        }

        // Clue card
        var dayOfWeek = getDayOfWeek(clue.puzzle_date);
        var formattedDate = formatDate(clue.puzzle_date);

        var metaItems = [];
        if (dayOfWeek && formattedDate) {
            metaItems.push(el('span', { className: 'kealoa-game__clue-date', textContent: dayOfWeek + ', ' + formattedDate }));
        }
        if (clue.constructors) {
            metaItems.push(el('span', { className: 'kealoa-game__clue-constructors', textContent: 'By: ' + clue.constructors }));
        }
        if (clue.editor) {
            metaItems.push(el('span', { className: 'kealoa-game__clue-editor', textContent: 'Ed: ' + clue.editor }));
        }

        var clueCard = el('div', { className: 'kealoa-game__clue-card' }, [
            el('div', { className: 'kealoa-game__clue-meta' }, metaItems),
            el('div', { className: 'kealoa-game__clue-text' }, [
                el('span', { className: 'kealoa-game__clue-label', textContent: 'Clue:' }),
                el('span', { textContent: ' ' + clue.clue_text })
            ])
        ]);

        // Answer buttons — the possible answers are the solution words
        var answerSection = el('div', { className: 'kealoa-game__answers' }, [
            el('p', { className: 'kealoa-game__choose-prompt', textContent: 'Choose the correct answer:' })
        ]);

        roundData.solution_words.forEach(function (word) {
            var btn = el('button', {
                type: 'button',
                className: 'kealoa-game__answer-btn',
                textContent: word,
                onClick: function () { handleAnswer(word); }
            });
            answerSection.appendChild(btn);
        });

        // Running score
        var correctSoFar = userAnswers.filter(function (a) { return a.correct; }).length;
        var scoreDisplay = el('div', { className: 'kealoa-game__running-score' }, [
            el('span', { textContent: 'Your score: ' + correctSoFar + '/' + (clueNum - 1) })
        ]);

        container.appendChild(progressBar);
        container.appendChild(roundInfo);
        if (roundDetails) {
            container.appendChild(roundDetails);
        }
        container.appendChild(clueCard);
        container.appendChild(answerSection);
        if (clueNum > 1) {
            container.appendChild(scoreDisplay);
        }
    }

    function handleAnswer(chosenWord) {
        var clue = roundData.clues[currentClueIndex];
        var isCorrect = chosenWord.toUpperCase() === clue.correct_answer.toUpperCase();

        userAnswers.push({
            clue_number: currentClueIndex + 1,
            chosen: chosenWord,
            correct_answer: clue.correct_answer,
            correct: isCorrect
        });

        showClueResult(chosenWord, isCorrect);
    }

    function showClueResult(chosenWord, isCorrect) {
        var clue = roundData.clues[currentClueIndex];
        var clueNum = currentClueIndex + 1;
        var totalClues = roundData.clues.length;

        container.innerHTML = '';

        // Progress bar
        var progressPct = (clueNum / totalClues * 100).toFixed(1);
        var progressBar = el('div', { className: 'kealoa-game__progress' }, [
            el('div', { className: 'kealoa-game__progress-bar', style: 'width:' + progressPct + '%' })
        ]);

        // Result header
        var resultClass = isCorrect ? 'kealoa-game__result--correct' : 'kealoa-game__result--incorrect';
        var resultText = isCorrect ? 'Correct!' : 'Incorrect';
        var resultHeader = el('div', { className: 'kealoa-game__result ' + resultClass }, [
            el('h3', { textContent: resultText })
        ]);

        // Show the clue again briefly
        var clueInfo = el('div', { className: 'kealoa-game__result-clue' }, [
            el('p', { className: 'kealoa-game__result-clue-text', innerHTML: '<strong>Clue:</strong> ' + escapeHtml(clue.clue_text) }),
            el('p', { className: 'kealoa-game__result-answer', innerHTML: '<strong>Correct Answer:</strong> ' + escapeHtml(clue.correct_answer) })
        ]);

        if (!isCorrect) {
            clueInfo.appendChild(
                el('p', { className: 'kealoa-game__result-your-answer', innerHTML: '<strong>Your Answer:</strong> ' + escapeHtml(chosenWord) })
            );
        }

        // Player guesses comparison
        var playersSection = el('div', { className: 'kealoa-game__player-guesses' }, [
            el('h4', { textContent: 'How the players answered:' })
        ]);

        var guessTable = el('table', { className: 'kealoa-game__guess-table' });
        var thead = el('thead', {}, [
            el('tr', {}, [
                el('th', { textContent: 'Player' }),
                el('th', { textContent: 'Answer' }),
                el('th', { textContent: 'Result' })
            ])
        ]);
        guessTable.appendChild(thead);

        var tbody = el('tbody');
        if (clue.guesses && clue.guesses.length) {
            clue.guesses.forEach(function (g) {
                var guessCorrect = g.is_correct;
                var resultIcon = guessCorrect ? '\u2713' : '\u2717';
                var rowClass = guessCorrect ? 'kealoa-game__guess--correct' : 'kealoa-game__guess--incorrect';
                tbody.appendChild(
                    el('tr', { className: rowClass }, [
                        el('td', { textContent: g.guesser_name }),
                        el('td', { textContent: g.guessed_word }),
                        el('td', { textContent: resultIcon })
                    ])
                );
            });
        } else {
            tbody.appendChild(
                el('tr', {}, [el('td', { colSpan: '3', textContent: 'No player data recorded.' })])
            );
        }
        guessTable.appendChild(tbody);
        playersSection.appendChild(guessTable);

        // Next button
        var isLast = currentClueIndex >= totalClues - 1;
        var nextBtn = el('button', {
            type: 'button',
            className: 'kealoa-game__next-btn',
            textContent: isLast ? 'See Results' : 'Next Clue \u2192',
            onClick: function () {
                if (isLast) {
                    showFinalResults();
                } else {
                    currentClueIndex++;
                    showClue();
                }
            }
        });

        container.appendChild(progressBar);
        container.appendChild(resultHeader);
        container.appendChild(clueInfo);
        container.appendChild(playersSection);
        container.appendChild(nextBtn);
    }

    function showFinalResults() {
        var totalClues = roundData.clues.length;
        var userCorrect = userAnswers.filter(function (a) { return a.correct; }).length;
        var userPct = totalClues > 0 ? (userCorrect / totalClues * 100) : 0;

        container.innerHTML = '';

        // Title
        container.appendChild(
            el('h2', { className: 'kealoa-game__results-title', textContent: 'Round Complete!' })
        );

        container.appendChild(
            el('p', { className: 'kealoa-game__results-round', textContent: 'KEALOA #' + roundData.round_id + ' \u2014 ' + roundData.solution_words.join(' / ') })
        );

        // Scoreboard
        var scores = [];

        // Add user score
        scores.push({
            name: 'You',
            correct: userCorrect,
            total: totalClues,
            pct: userPct,
            isUser: true
        });

        // Add player scores from guesser_results
        if (roundData.guesser_results) {
            roundData.guesser_results.forEach(function (gr) {
                var total = parseInt(gr.total_guesses, 10) || 0;
                var correct = parseInt(gr.correct_guesses, 10) || 0;
                var pct = total > 0 ? (correct / total * 100) : 0;
                scores.push({
                    name: gr.full_name,
                    correct: correct,
                    total: total,
                    pct: pct,
                    isUser: false
                });
            });
        }

        // Sort by correct count descending, then by name
        scores.sort(function (a, b) {
            if (b.correct !== a.correct) return b.correct - a.correct;
            if (a.isUser) return -1;
            if (b.isUser) return 1;
            return a.name.localeCompare(b.name);
        });

        var scoreTable = el('table', { className: 'kealoa-game__score-table' });
        var thead = el('thead', {}, [
            el('tr', {}, [
                el('th', { className: 'kealoa-num', textContent: '#' }),
                el('th', { textContent: 'Player' }),
                el('th', { className: 'kealoa-num', textContent: 'Score' }),
                el('th', { className: 'kealoa-num', textContent: 'Accuracy' })
            ])
        ]);
        scoreTable.appendChild(thead);

        var tbody = el('tbody');
        var rank = 0;
        var prevCorrect = -1;
        scores.forEach(function (s, i) {
            if (s.correct !== prevCorrect) {
                rank = i + 1;
                prevCorrect = s.correct;
            }
            var rowClass = s.isUser ? 'kealoa-game__score-row--user' : '';
            tbody.appendChild(
                el('tr', { className: rowClass }, [
                    el('td', { className: 'kealoa-num', textContent: String(rank) }),
                    el('td', { textContent: s.name + (s.isUser ? ' \u2b50' : '') }),
                    el('td', { className: 'kealoa-num', textContent: s.correct + '/' + s.total }),
                    el('td', { className: 'kealoa-num', textContent: s.pct.toFixed(1) + '%' })
                ])
            );
        });
        scoreTable.appendChild(tbody);
        container.appendChild(scoreTable);

        // Share results
        var shareText = buildShareText(userCorrect, totalClues, userAnswers);
        var shareSection = el('div', { className: 'kealoa-game__share' }, [
            el('button', {
                type: 'button',
                className: 'kealoa-game__share-btn',
                textContent: '\uD83D\uDCE4 Share Results',
                onClick: function () { shareResults(shareText, this); }
            })
        ]);
        container.appendChild(shareSection);

        // Clue-by-clue review table
        container.appendChild(
            el('h3', { className: 'kealoa-game__review-title', textContent: 'Clue-by-Clue Review' })
        );

        // Build review data sorted by original clue number (show order)
        var reviewData = userAnswers.map(function (answer, idx) {
            return { answer: answer, clue: roundData.clues[idx] };
        });
        reviewData.sort(function (a, b) {
            return (a.clue.clue_number || 0) - (b.clue.clue_number || 0);
        });

        var reviewTable = el('table', { className: 'kealoa-table kealoa-game__review-table' });

        var reviewThead = el('thead', {}, [
            el('tr', {}, [
                el('th', { className: 'kealoa-num', textContent: '#' }),
                el('th', { textContent: 'Day' }),
                el('th', { textContent: 'Puzzle Date' }),
                el('th', { textContent: 'Constructors' }),
                el('th', { textContent: 'Editor' }),
                el('th', { textContent: 'Clue #' }),
                el('th', { textContent: 'Clue Text' }),
                el('th', { textContent: 'Answer' }),
                el('th', { textContent: 'Your Result' })
            ])
        ]);
        reviewTable.appendChild(reviewThead);

        var reviewTbody = el('tbody');
        reviewData.forEach(function (item) {
            var clue = item.clue;
            var answer = item.answer;

            // Format day abbreviation from puzzle_date
            var dayAbbrev = '\u2014';
            if (clue.puzzle_date) {
                var d = new Date(clue.puzzle_date + 'T00:00:00');
                dayAbbrev = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][d.getDay()] || '\u2014';
            }

            // Format puzzle date as M/D/YYYY
            var fmtDate = '\u2014';
            if (clue.puzzle_date) {
                var dp = new Date(clue.puzzle_date + 'T00:00:00');
                fmtDate = (dp.getMonth() + 1) + '/' + dp.getDate() + '/' + dp.getFullYear();
            }

            // Format clue ref (e.g. "42D")
            var clueRef = '\u2014';
            if (clue.puzzle_clue_number && clue.puzzle_clue_direction) {
                clueRef = clue.puzzle_clue_number + clue.puzzle_clue_direction.toUpperCase();
            }

            // Player result
            var resultIcon = answer.correct ? '\u2713' : '\u2717';
            var resultText = resultIcon + ' ' + answer.chosen.toUpperCase();
            var resultClass = answer.correct ? 'kealoa-guess-correct' : 'kealoa-guess-incorrect';

            reviewTbody.appendChild(
                el('tr', {}, [
                    el('td', { className: 'kealoa-num', textContent: String(clue.clue_number || '') }),
                    el('td', { textContent: dayAbbrev }),
                    el('td', { textContent: fmtDate }),
                    el('td', { textContent: clue.constructors || '\u2014' }),
                    el('td', { textContent: clue.editor || '\u2014' }),
                    el('td', { textContent: clueRef }),
                    el('td', { textContent: clue.clue_text }),
                    el('td', {}, [
                        el('strong', { textContent: clue.correct_answer })
                    ]),
                    el('td', {}, [
                        el('span', { className: resultClass, textContent: resultText })
                    ])
                ])
            );
        });
        reviewTable.appendChild(reviewTbody);
        container.appendChild(reviewTable);

        // View round link
        container.appendChild(
            el('p', { className: 'kealoa-game__round-link' }, [
                el('a', {
                    href: roundData.round_url,
                    textContent: 'View full round details \u2192'
                })
            ])
        );

        // Spoiler description (shown only after the game is complete)
        if (roundData.description2) {
            container.appendChild(
                el('p', { className: 'kealoa-game__round-description kealoa-game__round-description--spoiler', textContent: roundData.description2 })
            );
        }

        // Play another round
        container.appendChild(
            el('div', { className: 'kealoa-game__play-again' }, [
                el('p', { className: 'kealoa-game__play-again-label', textContent: 'Play Another Round!' }),
                el('div', { className: 'kealoa-game__mode-buttons' }, [
                    el('button', {
                        type: 'button',
                        className: 'kealoa-game__start-btn',
                        textContent: 'In Show Order',
                        onClick: function () { startGame('show'); }
                    }),
                    el('button', {
                        type: 'button',
                        className: 'kealoa-game__start-btn',
                        textContent: 'In Random Order',
                        onClick: function () { startGame('random'); }
                    })
                ])
            ])
        );
    }

    function buildShareText(correct, total, answers) {
        // Sort answers back to show order for the emoji grid
        var sorted = answers.map(function (a, idx) {
            return { answer: a, clue: roundData.clues[idx] };
        });
        sorted.sort(function (a, b) {
            return (a.clue.clue_number || 0) - (b.clue.clue_number || 0);
        });

        var grid = sorted.map(function (item) {
            return item.answer.correct ? '\uD83D\uDFE9' : '\uD83D\uDFE5';
        }).join('');

        var lines = [
            'KEALOA #' + roundData.round_id + ' \u2014 ' + roundData.solution_words.join(' / '),
            correct + '/' + total,
            grid,
            roundData.round_url
        ];
        return lines.join('\n');
    }

    function shareResults(text, btnElement) {
        if (navigator.share) {
            navigator.share({ text: text }).catch(function () {
                // User cancelled or share failed — fall back to clipboard
                copyToClipboard(text, btnElement);
            });
        } else {
            copyToClipboard(text, btnElement);
        }
    }

    function copyToClipboard(text, btnElement) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                showCopiedFeedback(btnElement);
            }).catch(function () {
                fallbackCopy(text, btnElement);
            });
        } else {
            fallbackCopy(text, btnElement);
        }
    }

    function fallbackCopy(text, btnElement) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            showCopiedFeedback(btnElement);
        } catch (e) {
            // Silently fail
        }
        document.body.removeChild(ta);
    }

    function showCopiedFeedback(btnElement) {
        var original = btnElement.textContent;
        btnElement.textContent = '\u2705 Copied!';
        btnElement.classList.add('kealoa-game__share-btn--copied');
        setTimeout(function () {
            btnElement.textContent = original;
            btnElement.classList.remove('kealoa-game__share-btn--copied');
        }, 2000);
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // =========================================================================
    // Init — attach start button handler
    // =========================================================================

    var startBtns = container.querySelectorAll('.kealoa-game__start-btn');
    startBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            startGame(btn.getAttribute('data-mode') || 'show');
        });
    });

    } // end initGame
})();
