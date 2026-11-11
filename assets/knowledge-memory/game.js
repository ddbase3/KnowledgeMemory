(function(global) {
	'use strict';

	const LAYOUTS = Object.freeze(['classic', 'staggered', 'scattered', 'orbit', 'wave']);

	function shuffle(cards, random = Math.random) {
		for (let index = cards.length - 1; index > 0; index--) {
			const other = Math.floor(random() * (index + 1));
			[cards[index], cards[other]] = [cards[other], cards[index]];
		}
		return cards;
	}

	function seededRandom(seed) {
		let value = seed >>> 0;
		return function() {
			value += 0x6D2B79F5;
			let next = value;
			next = Math.imul(next ^ (next >>> 15), next | 1);
			next ^= next + Math.imul(next ^ (next >>> 7), next | 61);
			return ((next ^ (next >>> 14)) >>> 0) / 4294967296;
		};
	}

	function selectLayout(previous = null, random = Math.random) {
		const choices = LAYOUTS.filter(name => name !== previous);
		const index = Math.min(choices.length - 1, Math.max(0, Math.floor(random() * choices.length)));
		return choices[index];
	}

	function chooseColumns(width, count) {
		const available = width >= 920 ? 6 : width >= 740 ? 5 : width >= 580 ? 4 : width >= 430 ? 3 : width >= 265 ? 2 : 1;
		return Math.min(available, Math.max(2, Math.ceil(Math.sqrt(count) * 1.5)));
	}

	function computeLayout(count, width, mode, seed = 1, viewportHeight = 620) {
		if (!Number.isInteger(count) || count < 1 || !Number.isFinite(width) || width <= 0
			|| !Number.isFinite(viewportHeight) || viewportHeight < 280 || !LAYOUTS.includes(mode)) {
			throw new TypeError('Invalid memory layout');
		}
		const random = seededRandom(seed);
		const positions = [];
		if (mode === 'orbit' || mode === 'scattered') {
			const cardWidth = Math.round(Math.max(102, Math.min(190, width * (width >= 680 ? .23 : .38))));
			const cardHeight = Math.round(Math.max(144, Math.min(190, cardWidth * 1.04)));
			let boardHeight;
			if (mode === 'orbit') {
				const radius = Math.max(12, Math.min((width - cardWidth) / 2 - 18, (viewportHeight - cardHeight - 28) / 2));
				boardHeight = Math.max(280, Math.ceil(cardHeight + 2 * radius + 28));
				const outerCount = count > 16 ? Math.ceil(count * .66) : count;
				const innerCount = count - outerCount;
				for (let index = 0; index < count; index++) {
					const innerRing = index >= outerCount;
					const ringCount = innerRing ? innerCount : outerCount;
					const ringIndex = innerRing ? index - outerCount : index;
					const angle = (2 * Math.PI * ringIndex / ringCount) - Math.PI / 2 + (innerRing ? Math.PI / ringCount : 0);
					const ringRadius = innerRing ? radius * .54 : radius;
					positions.push({
						row: 0, col: 0, span: 1,
						x: Math.round(width / 2 + Math.cos(angle) * ringRadius - cardWidth / 2),
						y: Math.round(boardHeight / 2 + Math.sin(angle) * ringRadius - cardHeight / 2),
						angle: 0
					});
				}
			} else {
				const columns = Math.max(2, Math.min(6, Math.floor(width / 154)));
				const rows = Math.ceil(count / columns);
				boardHeight = Math.max(350, Math.ceil(rows * cardHeight * .78 + 65));
				const cells = [];
				for (let row = 0; row < rows; row++) {
					for (let col = 0; col < columns; col++) cells.push({row, col});
				}
				shuffle(cells, random);
				for (let index = 0; index < count; index++) {
					const cell = cells[index];
					const angle = Math.round((random() * 30 - 15) * 10) / 10;
					const rad = Math.abs(angle) * Math.PI / 180;
					const extentX = (cardWidth * Math.cos(rad) + cardHeight * Math.sin(rad)) / 2;
					const extentY = (cardHeight * Math.cos(rad) + cardWidth * Math.sin(rad)) / 2;
					const centerX = (cell.col + .5 + (random() - .5) * .72) * width / columns;
					const centerY = (cell.row + .5 + (random() - .5) * .72) * boardHeight / rows;
					positions.push({
						row: cell.row, col: cell.col, span: 1,
						x: Math.round(Math.max(extentX + 9, Math.min(width - extentX - 9, centerX)) - cardWidth / 2),
						y: Math.round(Math.max(extentY + 9, Math.min(boardHeight - extentY - 9, centerY)) - cardHeight / 2),
						angle
					});
				}
			}
			return {
				mode, absolute: true, columns: 1, rows: 1, cardWidth, cardHeight, boardHeight,
				horizontalGap: 0, verticalGap: 0, positions
			};
		}

		const columns = chooseColumns(width, count);
		let rows = Math.ceil(count / columns);
		const smallScreen = columns <= 2;
		const gap = smallScreen ? 11 : 15;
		const verticalGap = mode === 'wave' ? 33 : 17;
		const innerWidth = Math.max(1, width - 28);
		const cardWidth = (innerWidth - (columns - 1) * gap) / columns;
		const cardHeight = Math.max(154, Math.min(196, Math.round(cardWidth * .93)));
		let gridColumns = columns;
		let horizontalGap = gap;
		if (mode === 'staggered' && columns > 2) {
			gridColumns = columns * 2;
			horizontalGap = 0;
			let index = 0;
			let row = 0;
			while (index < count) {
				const shifted = row % 2 === 1;
				const capacity = shifted ? columns - 1 : columns;
				for (let col = 0; col < capacity && index < count; col++) {
					positions.push({row, col: col * 2 + (shifted ? 1 : 0), span: 2, x: 0, y: 0, angle: 0});
					index++;
				}
				row++;
			}
			rows = row;
		} else {
			for (let index = 0; index < count; index++) {
				const col = index % columns;
				const row = Math.floor(index / columns);
				const wave = mode === 'wave' ? Math.round(Math.sin(col * 1.12 + row * .45) * 12) : 0;
				const stagger = mode === 'staggered' && smallScreen ? (row % 2 ? 5 : -5) : 0;
				positions.push({row, col, span: 1, x: stagger, y: wave, angle: 0});
			}
		}
		return {mode, absolute: false, columns: gridColumns, rows, cardWidth, cardHeight, horizontalGap, verticalGap, positions};
	}

	function chooseSeedTriples(keywords, count, used = new Set(), random = Math.random) {
		if (!Array.isArray(keywords) || !Number.isInteger(count) || count < 1 || count > 12) {
			throw new TypeError('Invalid keywords or pair count');
		}
		const terms = [...new Set(keywords)];
		const size = terms.length;
		const total = size * (size - 1) * (size - 2) / 6;
		if (size < 3 || !Number.isSafeInteger(total) || total < count) {
			throw new TypeError('Insufficient distinct keyword combinations');
		}
		const chosen = [];
		const selectedRanks = new Map();
		for (let offset = 0; offset < total && chosen.length < count; offset++) {
			const index = offset + Math.floor(random() * (total - offset));
			const rank = selectedRanks.has(index) ? selectedRanks.get(index) : index;
			selectedRanks.set(index, selectedRanks.has(offset) ? selectedRanks.get(offset) : offset);
			let remainder = rank;
			let a = 0;
			while (remainder >= (size - a - 1) * (size - a - 2) / 2) {
				remainder -= (size - a - 1) * (size - a - 2) / 2;
				a++;
			}
			let b = a + 1;
			while (remainder >= size - b - 1) {
				remainder -= size - b - 1;
				b++;
			}
			const triple = [terms[a], terms[b], terms[b + 1 + remainder]];
			const signature = [...triple].sort().join('|');
			if (!used.has(signature)) chosen.push(shuffle(triple, random));
		}
		if (chosen.length !== count) throw new Error('Not enough unused keyword combinations');
		return chosen;
	}

	function normalizedCardText(value) {
		return String(value).normalize('NFKC').toLocaleLowerCase().replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
	}

	function isDuplicatePair(candidate, accepted) {
		if (!candidate || typeof candidate.question !== 'string' || typeof candidate.answer !== 'string') return true;
		const question = normalizedCardText(candidate.question);
		const answer = normalizedCardText(candidate.answer);
		if (!question || !answer) return true;
		return accepted.some(pair => normalizedCardText(pair.question) === question || normalizedCardText(pair.answer) === answer);
	}

	class GameState {
		constructor(pairs) {
			if (!Array.isArray(pairs) || pairs.length < 1) throw new TypeError('Pairs required');
			this.cards = shuffle(pairs.flatMap((pair, index) => [
				{pair: index, kind: 'question', text: pair.question, exposed: false, matched: false},
				{pair: index, kind: 'answer', text: pair.answer, exposed: false, matched: false}
			]));
			this.total = pairs.length;
			this.selected = [];
			this.matches = 0;
			this.attempts = 0;
			this.misses = 0;
			this.streak = 0;
			this.score = 0;
			this.locked = false;
			this.completed = false;
		}

		flip(index) {
			if (!Number.isInteger(index) || index < 0 || index >= this.cards.length || this.locked || this.completed) {
				return 'ignored';
			}
			const card = this.cards[index];
			if (card.exposed || card.matched) return 'ignored';
			card.exposed = true;
			this.selected.push(index);
			if (this.selected.length === 1) return 'first';

			this.attempts++;
			const first = this.cards[this.selected[0]];
			const second = this.cards[this.selected[1]];
			if (first.pair === second.pair && first.kind !== second.kind) {
				first.matched = true;
				second.matched = true;
				this.matches++;
				this.streak++;
				this.score += 100 + Math.min(40, (this.streak - 1) * 10);
				this.selected = [];
				if (this.matches === this.total) {
					this.completed = true;
					return 'complete';
				}
				return 'match';
			}

			this.misses++;
			this.streak = 0;
			this.score = Math.max(0, this.score - 20);
			this.locked = true;
			return 'miss';
		}

		hideMismatch() {
			if (!this.locked) return;
			for (const index of this.selected) this.cards[index].exposed = false;
			this.selected = [];
			this.locked = false;
		}
	}

	function summarize(state) {
		if (!(state instanceof GameState)) throw new TypeError('Game state required');
		const accuracy = state.attempts ? Math.round(state.matches / state.attempts * 100) : 100;
		return {
			accuracy,
			stars: accuracy >= 85 ? 3 : accuracy >= 60 ? 2 : 1,
			attempts: state.attempts,
			misses: state.misses,
			score: state.score,
			matches: state.matches,
			total: state.total
		};
	}

	function formatTime(elapsedSeconds) {
		const seconds = Math.max(0, Math.floor(elapsedSeconds));
		return String(Math.floor(seconds / 60)).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0');
	}

	function mount(id, options) {
		const root = document.getElementById(id);
		if (!root || root.dataset.kmMounted === '1') return;
		root.dataset.kmMounted = '1';

		const t = options.texts;
		const start = root.querySelector('[data-km-action="start"]');
		const board = root.querySelector('[data-km-board]');
		const empty = root.querySelector('[data-km-empty]');
		const emptyTitle = root.querySelector('[data-km-empty-title]');
		const feedback = root.querySelector('[data-km-feedback]');
		const announcement = root.querySelector('[data-km-announcement]');
		const result = root.querySelector('[data-km-result]');
		const progress = root.querySelector('[data-km-progress]');
		const fill = root.querySelector('[data-km-progress-fill]');
		const percentage = root.querySelector('[data-km-percent]');
		const score = root.querySelector('[data-km-stat="score"]');
		const matches = root.querySelector('[data-km-stat="matches"]');
		const clock = root.querySelector('[data-km-stat="time"]');
		const scorePop = root.querySelector('[data-km-score-pop]');
		const combo = root.querySelector('[data-km-combo]');
		const layoutLabel = root.querySelector('[data-km-layout-label]');
		const fullscreen = root.querySelector('[data-km-action="fullscreen"]');
		const seedStage = root.querySelector('[data-km-seed-stage]');
		const seedHeading = root.querySelector('[data-km-seed-heading]');
		const seedCloud = root.querySelector('[data-km-seed-cloud]');
		const seedCombinations = root.querySelector('[data-km-seed-combinations]');
		let seedBatch = null;
		let seedProgressCopy = null;
		let seedProgressTrack = null;
		let seedProgressFill = null;
		const displayedTriples = new Map();
		let state = null;
		let pairs = [];
		let startedAt = 0;
		let finishedAt = 0;
		let ticker = null;
		let mismatchTimer = null;
		let lastLayout = null;
		let activeLayout = null;
		let layoutSeed = 1;
		let observedWidth = 0;

		function createElement(tag, className, content) {
			const element = document.createElement(tag);
			if (className) element.className = className;
			if (content !== undefined) element.textContent = content;
			return element;
		}

		function elapsed() {
			return ((finishedAt || Date.now()) - startedAt) / 1000;
		}

		function setFeedback(message, tone = 'neutral') {
			feedback.textContent = message;
			feedback.dataset.tone = tone;
		}

		function announceCard(index) {
			const card = state.cards[index];
			announcement.textContent = (card.kind === 'question' ? t.question : t.answer) + ': ' + card.text;
		}

		function paintStats() {
			score.textContent = state ? String(state.score) : '0';
			matches.textContent = state ? (state.matches + '/' + state.total) : '0/0';
			clock.textContent = state && startedAt ? formatTime(elapsed()) : '00:00';
			const value = state ? Math.round((state.matches / state.total) * 100) : 0;
			progress.setAttribute('aria-valuenow', String(value));
			fill.style.width = value + '%';
			percentage.textContent = value + '%';
			combo.hidden = !state || state.streak < 2 || state.completed;
			if (!combo.hidden) combo.textContent = t.combo + ' ×' + state.streak;
		}

		function showPoints(delta) {
			if (!delta) return;
			scorePop.textContent = (delta > 0 ? '+' : '') + delta;
			scorePop.classList.remove('km-game__score-pop--show');
			void scorePop.offsetWidth;
			scorePop.dataset.tone = delta > 0 ? 'positive' : 'negative';
			scorePop.classList.add('km-game__score-pop--show');
		}

		function paintCards() {
			if (!state) return;
			state.cards.forEach((card, index) => {
				const element = board.children[index];
				const active = card.exposed || card.matched;
				element.classList.toggle('km-card--open', active);
				element.classList.toggle('km-card--matched', card.matched);
				element.classList.toggle('km-card--wrong', state.locked && state.selected.includes(index));
				element.setAttribute('aria-disabled', card.matched || state.locked ? 'true' : 'false');
				element.tabIndex = card.matched ? -1 : 0;
				element.setAttribute('aria-label', active
					? ((card.kind === 'question' ? t.question : t.answer) + ': ' + card.text + (card.matched ? '. ' + t.matched_card : ''))
					: t.hidden_card + ' ' + (index + 1));
				if (active) element.querySelector('.km-card__content').textContent = card.text;
			});
			paintStats();
		}

		function applyLayout() {
			if (!state || !activeLayout) return;
			const width = Math.round(board.getBoundingClientRect().width);
			if (width < 1) return;
			observedWidth = width;
			const availableHeight = Math.max(300, Math.min(620, global.innerHeight - root.getBoundingClientRect().top - 75));
			const layout = computeLayout(state.cards.length, width, activeLayout, layoutSeed, availableHeight);
			board.dataset.layout = activeLayout;
			board.style.setProperty('--km-cols', String(layout.columns));
			board.style.setProperty('--km-card-height', layout.cardHeight + 'px');
			board.style.setProperty('--km-column-gap', layout.horizontalGap + 'px');
			board.style.setProperty('--km-row-gap', layout.verticalGap + 'px');
			board.style.gridTemplateRows = layout.absolute ? 'none' : 'repeat(' + layout.rows + ', var(--km-card-height))';
			board.style.height = layout.absolute ? layout.boardHeight + 'px' : '';
			layout.positions.forEach((position, index) => {
				const element = board.children[index];
				element.style.gridColumn = layout.absolute ? '' : (position.col + 1) + ' / span ' + position.span;
				element.style.gridRow = layout.absolute ? '' : String(position.row + 1);
				element.style.setProperty('--km-offset-x', layout.absolute ? '0px' : position.x + 'px');
				element.style.setProperty('--km-offset-y', layout.absolute ? '0px' : position.y + 'px');
				element.style.setProperty('--km-angle', position.angle + 'deg');
				if (layout.absolute) {
					element.style.left = position.x + 'px';
					element.style.top = position.y + 'px';
					element.style.width = layout.cardWidth + 'px';
					element.style.height = layout.cardHeight + 'px';
					element.style.setProperty('--km-order', String(index + 1));
				} else {
					element.style.left = '';
					element.style.top = '';
					element.style.width = '';
					element.style.height = '';
				}
			});
		}

		function renderBoard() {
			board.replaceChildren();
			for (let index = 0; index < state.cards.length; index++) {
				const card = state.cards[index];
				const button = createElement('button', 'km-card');
				button.type = 'button';
				button.dataset.index = String(index);
				const inner = createElement('span', 'km-card__inner');
				const back = createElement('span', 'km-card__face km-card__face--back');
				back.setAttribute('aria-hidden', 'true');
				back.append(createElement('span', 'km-card__back-orbit'), createElement('span', 'km-card__back-mark', '✦'));
				const front = createElement('span', 'km-card__face km-card__face--front');
				front.setAttribute('aria-hidden', 'true');
				front.dataset.kind = card.kind;
				front.append(
					createElement('span', 'km-card__type', card.kind === 'question' ? t.question : t.answer),
					createElement('span', 'km-card__content')
				);
				inner.append(back, front);
				button.append(inner);
				button.addEventListener('click', () => handleFlip(index));
				board.append(button);
			}
			empty.hidden = true;
			layoutLabel.textContent = t['layout_' + activeLayout];
			applyLayout();
			paintCards();
		}

		function focusNextCard(index) {
			if (state.completed) return;
			for (let step = 1; step <= state.cards.length; step++) {
				const next = (index + step) % state.cards.length;
				if (!state.cards[next].matched) {
					board.children[next].focus({preventScroll: true});
					return;
				}
			}
		}

		function showResult() {
			const summaryData = summarize(state);
			const accuracy = summaryData.accuracy;
			const stars = summaryData.stars;
			const heading = createElement('h3', 'km-game__result-title', t.completed);
			const kicker = createElement('p', 'km-game__result-kicker', t.result_kicker);
			const starLine = createElement('div', 'km-game__result-stars');
			starLine.setAttribute('aria-label', t.rating + ': ' + stars + ' / 3');
			for (let index = 0; index < 3; index++) {
				const star = createElement('span', index < stars ? 'km-game__star--earned' : 'km-game__star--empty', '★');
				star.setAttribute('aria-hidden', 'true');
				starLine.append(star);
			}
			const points = createElement('p', 'km-game__result-score', String(state.score));
			const pointsLabel = createElement('p', 'km-game__result-score-label', t.points);
			const stats = createElement('div', 'km-game__result-stats');
			for (const [value, label] of [
				[state.matches + '/' + state.total, t.matches],
				[accuracy + '%', t.accuracy],
				[formatTime(elapsed()), t.time]
			]) {
				const cell = createElement('div', 'km-game__result-stat');
				cell.append(createElement('strong', '', value), createElement('span', '', label));
				stats.append(cell);
			}
			const sources = createElement('details', 'km-game__review');
			const summary = createElement('summary', '', t.review);
			sources.append(summary, createElement('p', 'km-game__review-notice', t.source_note));
			const list = createElement('ol', 'km-game__review-list');
			for (const pair of pairs) {
				const item = document.createElement('li');
				const question = createElement('strong', '', pair.question);
				const answer = createElement('p', '', pair.answer);
				item.append(question, answer);
				if (pair.source_excerpt) item.append(createElement('blockquote', '', pair.source_excerpt));
				if (pair.source_title) item.append(createElement('cite', '', pair.source_title));
				list.append(item);
			}
			sources.append(list);
			result.replaceChildren(kicker, heading, starLine, points, pointsLabel, stats, sources);
			result.hidden = false;
			result.focus({preventScroll: true});
			start.textContent = t.play_again;
			start.disabled = false;
			setFeedback(t.completed, 'success');
		}

		function handleFlip(index) {
			if (!state) return;
			const before = state.score;
			const action = state.flip(index);
			if (action === 'ignored') return;
			paintCards();
			announceCard(index);
			if (action === 'first') {
				setFeedback(t.choose_second);
				return;
			}
			showPoints(state.score - before);
			if (action === 'miss') {
				setFeedback(t.wrong_pair, 'error');
				mismatchTimer = setTimeout(() => {
					state.hideMismatch();
					paintCards();
					setFeedback(t.try_again);
				}, 1100);
				return;
			}
			setFeedback(t.correct_pair, 'success');
			if (action === 'complete') {
				finishedAt = Date.now();
				clearInterval(ticker);
				showResult();
			} else {
				focusNextCard(index);
			}
		}

		function showKeywordPool(keywords) {
			seedCloud.replaceChildren();
			seedCombinations.replaceChildren();
			for (let index = 0; index < keywords.length; index++) {
				const chip = createElement('span', 'km-game__seed-word', keywords[index]);
				chip.style.setProperty('--km-seed-delay', '-' + (index * .13).toFixed(2) + 's');
				chip.style.setProperty('--km-seed-drift', (index % 2 ? -7 : 7) + 'px');
				seedCloud.append(chip);
			}
			seedHeading.textContent = t.loading_cards;
		}

		function showSelectedTriples(activeTriples, done, target) {
			const chosen = new Set([...activeTriples.values()].flat());
			for (const chip of seedCloud.children) {
				chip.classList.toggle('km-game__seed-word--selected', chosen.has(chip.textContent));
			}
			if (seedBatch === null) {
				const caption = createElement('p', 'km-game__seed-caption', t.seed_current);
				seedBatch = createElement('div', 'km-game__seed-batch');
				seedProgressCopy = createElement('p', 'km-game__seed-progress-copy');
				seedProgressTrack = createElement('div', 'km-game__seed-progress-track');
				seedProgressTrack.setAttribute('role', 'progressbar');
				seedProgressTrack.setAttribute('aria-label', t.seed_progress_label);
				seedProgressTrack.setAttribute('aria-valuemin', '0');
				seedProgressTrack.setAttribute('aria-valuemax', String(target));
				seedProgressFill = createElement('span', 'km-game__seed-progress-fill');
				seedProgressTrack.append(seedProgressFill);
				seedCombinations.replaceChildren(caption, seedBatch, seedProgressCopy, seedProgressTrack);
			}
			for (const [attempt, triple] of activeTriples) {
				if (displayedTriples.has(attempt)) continue;
				const group = createElement('div', 'km-game__seed-group');
				group.setAttribute('role', 'group');
				group.setAttribute('aria-label', t.seed_current + ' ' + attempt);
				const number = createElement('span', 'km-game__seed-attempt', '#' + attempt);
				const focus = createElement('div', 'km-game__seed-focus');
				for (let index = 0; index < triple.length; index++) {
					const chip = createElement('span', 'km-game__seed-focus-word', triple[index]);
					chip.style.setProperty('--km-draw-delay', index * 130 + 'ms');
					focus.append(chip);
				}
				group.append(number, focus);
				seedBatch.append(group);
				displayedTriples.set(attempt, group);
			}
			for (const [attempt, group] of displayedTriples) {
				if (activeTriples.has(attempt)) continue;
				group.remove();
				displayedTriples.delete(attempt);
			}
			seedProgressCopy.textContent = t.seed_progress.replace('{done}', String(done)).replace('{total}', String(target));
			seedProgressTrack.setAttribute('aria-valuenow', String(done));
			seedProgressFill.style.width = (100 * done / target) + '%';
			seedHeading.textContent = t.loading_cards;
		}

		async function requestGame(fields) {
			const response = await fetch(options.apiUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'Accept': 'application/json'},
				body: new URLSearchParams(fields)
			});
			const payload = await response.json();
			if (!response.ok || !payload.ok) {
				const message = typeof payload.error === 'string' && payload.error.trim() ? payload.error : null;
				const error = new Error(message || t.generation_failed);
				error.status = response.status;
				throw error;
			}
			return payload;
		}

		async function newRound() {
			start.disabled = true;
			start.textContent = t.loading;
			setFeedback(t.loading_keywords);
			result.hidden = true;
			board.replaceChildren();
			board.removeAttribute('data-layout');
			board.style.height = '';
			layoutLabel.textContent = t.layout_pending;
			empty.hidden = true;
			seedStage.hidden = false;
			seedCloud.replaceChildren();
			seedCombinations.replaceChildren();
			seedBatch = null;
			seedProgressCopy = null;
			seedProgressTrack = null;
			seedProgressFill = null;
			displayedTriples.clear();
			seedHeading.textContent = t.loading_keywords;
			clearInterval(ticker);
			clearTimeout(mismatchTimer);
			scorePop.classList.remove('km-game__score-pop--show');
			state = null;
			pairs = [];
			announcement.textContent = '';
			startedAt = 0;
			finishedAt = 0;
			observedWidth = 0;
			paintStats();
			try {
				const found = await requestGame({action: 'keywords'});
				if (!Array.isArray(found.keywords) || !Number.isInteger(found.pairs_count) || found.pairs_count < 1) {
					throw new Error(t.keywords_failed);
				}
				showKeywordPool(found.keywords);
				setFeedback(t.loading_cards);
				const accepted = [];
				const activeTriples = new Map();
				const usedTriples = new Set();
				const limit = found.pairs_count * 6;
				let attempts = 0;
				let fatalError = null;

				async function generatePair() {
					while (!fatalError && accepted.length < found.pairs_count && attempts < limit) {
						if (accepted.length + activeTriples.size >= found.pairs_count) return;
						const [triple] = chooseSeedTriples(found.keywords, 1, usedTriples);
						usedTriples.add([...triple].sort().join('|'));
						const attempt = ++attempts;
						const excluded = accepted.map(pair => pair.answer);
						const fields = {action: 'cards', exclude_count: String(excluded.length)};
						for (let part = 0; part < 3; part++) fields['seed_0_' + part] = triple[part];
						for (let index = 0; index < excluded.length; index++) fields['exclude_' + index] = excluded[index];
						activeTriples.set(attempt, triple);
						showSelectedTriples(activeTriples, accepted.length, found.pairs_count);
						try {
							const payload = await requestGame(fields);
							const candidate = Array.isArray(payload.pairs) ? payload.pairs[0] : null;
							if (accepted.length < found.pairs_count && !isDuplicatePair(candidate, accepted)) {
								accepted.push(candidate);
								setFeedback(t.seed_progress.replace('{done}', String(accepted.length)).replace('{total}', String(found.pairs_count)));
							} else if (accepted.length < found.pairs_count) {
								setFeedback(t.seed_duplicate);
							}
						} catch (error) {
							if (error && [403, 405, 422].includes(error.status)) fatalError = error;
						} finally {
							activeTriples.delete(attempt);
							showSelectedTriples(activeTriples, accepted.length, found.pairs_count);
						}
					}
				}
				await Promise.all(Array.from({length: Math.min(3, found.pairs_count)}, () => generatePair()));
				if (fatalError) throw fatalError;
				if (accepted.length !== found.pairs_count) throw new Error(t.seed_exhausted);
				pairs = accepted;
				state = new GameState(pairs);
				activeLayout = selectLayout(lastLayout);
				lastLayout = activeLayout;
				layoutSeed = Math.floor(Math.random() * 4294967296);
				seedStage.hidden = true;
				renderBoard();
				startedAt = Date.now();
				ticker = setInterval(paintStats, 1000);
				setFeedback(t.choose_first);
				start.textContent = t.restart;
				board.querySelector('.km-card')?.focus({preventScroll: true});
			} catch (error) {
				seedStage.hidden = true;
				empty.hidden = false;
				setFeedback(error instanceof Error ? error.message : t.generation_failed, 'error');
				emptyTitle.textContent = t.empty_title;
				start.textContent = t.try_again;
			}
			start.disabled = false;
		}

		start.addEventListener('click', newRound);
		if (fullscreen) {
			fullscreen.addEventListener('click', async () => {
				try {
					if (document.fullscreenElement === root) {
						await document.exitFullscreen();
					} else {
						await root.requestFullscreen();
					}
				} catch (error) {
					setFeedback(t.fullscreen_unavailable, 'error');
				}
			});
			document.addEventListener('fullscreenchange', () => {
				const active = document.fullscreenElement === root;
				fullscreen.setAttribute('aria-pressed', String(active));
				fullscreen.setAttribute('aria-label', active ? t.exit_fullscreen : t.fullscreen);
				fullscreen.title = active ? t.exit_fullscreen : t.fullscreen;
				if (state) applyLayout();
			});
		}
		if (typeof ResizeObserver === 'function') {
			const observer = new ResizeObserver(() => {
				if (state && Math.round(board.getBoundingClientRect().width) !== observedWidth) applyLayout();
			});
			observer.observe(board);
		} else {
			global.addEventListener('resize', applyLayout);
		}
		paintStats();
	}

	function mountSettings(root) {
		if (!root || root.dataset.kmSettingsMounted === '1') return;
		const config = JSON.parse(root.dataset.kmSettingsConfig);
		const form = root.querySelector('[data-km-settings-form]');
		const message = root.querySelector('[data-km-settings-message]');
		if (!form || !message) return;
		root.dataset.kmSettingsMounted = '1';
		form.addEventListener('submit', async event => {
			event.preventDefault();
			const button = form.querySelector('button[type="submit"]');
			button.disabled = true;
			message.textContent = '';
			try {
				const response = await fetch(config.apiUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'Accept': 'application/json'},
					body: new URLSearchParams({
						agent_id: form.elements.agent_id.value,
						pairs: form.elements.pairs.value,
						difficulty: form.elements.difficulty.value
					})
				});
				const data = await response.json();
				message.textContent = data.ok ? data.message : data.error;
			} catch (error) {
				message.textContent = config.saveFailed;
			} finally {
				button.disabled = false;
			}
		});
	}

	function mountAll() {
		for (const root of document.querySelectorAll('[data-km-settings-config]')) {
			mountSettings(root);
		}
		for (const root of document.querySelectorAll('[data-km-game-config]')) {
			mount(root.id, JSON.parse(root.dataset.kmGameConfig));
		}
	}

	global.KnowledgeMemoryGame = {mount, mountSettings, mountAll, GameState, summarize, computeLayout, selectLayout, seededRandom, chooseSeedTriples, isDuplicatePair, LAYOUTS};
	if (typeof document !== 'undefined') {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', mountAll, {once: true});
		} else {
			mountAll();
		}
	}
})(globalThis);
