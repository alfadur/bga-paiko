/**
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Paiko implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

const gameName = "paiko";

const State = {
    draft: "draft",
    action: "action",
    saiMove: "saiMove",
    reserve: "reserve",
    clientDraft: "clientDraft",
    clientDeploy: "clientDeploy",
    clientMove: "clientMove",
    clientConfirm: "clientConfirm"
}
Object.freeze(State);

const Action = {
    draft: "actDraft",
    move: "actMove",
    deploy: "actDeploy",
    skip: "actSkip",
    clientMove: "actClientMove",
};
Object.freeze(Action);

const PieceType = {
    sai: 0,
    sword: 1,
    bow: 2,
    lotus: 3,
    air: 4,
    fire: 5,
    earth: 6,
    water: 7
}
Object.freeze(PieceType);

const PieceStatus = {
    reserve: 0,
    hand: 1,
    board: 2
}
Object.freeze(PieceStatus);

const PieceThreat = [
    [[0, -1]],
    [[-1, -1], [0, -1], [1, -1], [-1, 0], [1, 0], [-1, 1], [0, 1], [1, 1]],
    [[0, -2], [0, -3], [0, -4]],
    [],
    [[-1, -2], [1, -2], [-2, -1], [2, -1], [-2, 1], [2, 1], [-1, 2], [1, 2]],
    [[-2, -2], [-1, -2], [0, -2], [1, -2], [2, -2], [-1, -1], [0, -1], [1, -1], [0, 0]],
    [[0, -2], [0, -1], [-2, 0], [-1, 0], [1, 0], [2, 0], [0, 1], [0, 2]],
    [[-1, -1], [1, -1], [-1, 1], [1, 1]]
];
Object.freeze(PieceThreat);

const PieceCover = [
    [[-1, 0], [1, 0]],
    [],
    [],
    [[-1, -1], [0, -1], [1, -1], [-1, 0], [0, 0], [1, 0], [-1, 1], [0, 1], [1, 1]],
    [],
    [],
    [],
    [[0, -1], [-1, 0], [1, 0], [0, 1]]
];
Object.freeze(PieceCover);

const Directions = [[0, -1], [1, 0], [0, 1], [-1, 0]]
    .map(([x, y]) => ({x, y}));
Object.freeze(Directions);

function range(count) {
    const result = [];
    if (arguments.length === 1) {
        for (let i = 0; i < count; ++i) {
            result.push(i);
        }
    }
    return result;
}

function intMod(a, b) {
    return (a % b + b) % b;
}

/**
 * @param {string} tag
 * @param {string} [selector]
 */
function clearTag(tag, selector) {
    const elements = document.querySelectorAll(
        selector ? `${selector.trim()}.${tag}` : `.${tag}`);
    for (const element of elements) {
        element.classList.remove(tag);
    }
}

/**
 * @param {Element} parent
 * @param {string} html
 * @param {number|null} position
 * @return {Element|null}
 */
function createElement(parent, html, position = null) {
    if (position !== null && position >= parent.childElementCount) {
        position = null;
    }
    position === null ?
        parent.insertAdjacentHTML("beforeend", html) :
        parent.children[position].insertAdjacentHTML("beforebegin", html);
    return position === null ? parent.lastElementChild : parent.children[position];
}

/**
 * @param {Element} element
 * @param {Object.<string, int|string>} style
 */
function setStyle(element, style) {
    for (const key of Object.keys(style)) {
        const name = "--" + key.replace(/([A-Z])/, (_, c) => "-" + c.toLowerCase())
        const value = style[key];

        if (value === undefined || value === null) {
            element.style.removeProperty(name);
        } else {
            element.style.setProperty(name, value);
        }
    }
}

function getStyle(element, style) {
    for (const key of Object.keys(style)) {
        const name = "--" + key.replace(/([A-Z])/, (_, c) => "-" + c.toLowerCase());
        style[key] = parseInt(element.style.getPropertyValue(name));
    }
    return style;
}

function createPiece(id, playerIndex, type, angle = 0) {
    const spriteX = type % 4;
    const spriteY = type >> 2;
    const idString = id === null ? "" : `id="pk-piece-${id}"`;
    const data = id === null ? "" : `data-id="${id}"`;
    return `<div ${idString} class="pk-piece" style="--angle: ${angle}; --sprite-x: ${spriteX}; --sprite-y: ${spriteY}" 
        data-type="${type}" data-player="${playerIndex}" ${data}>
        <div class="pk-piece-shadow"></div>
        <div class="pk-piece-base"></div>
        <div class="pk-piece-image"></div>
    </div>`;
}

function createBoard(playerIndex) {
    const spaces = [];
    for (let y = 0; y < 14; ++y) {
        const width = y < 7 ? y : 13 - y;
        for (let x = 6 - width; x < 7 + width + 1; ++x) {
            const isHole =
                x === 5 && y === 5
                || x === 8 && y === 8
                || x === 7 && y === 6
                || x === 6 && y === 7;
            const className = isHole ?
                "pk-board-hole" : "pk-board-space";

            let column = x < 7 ? x + 2 : x + 3;
            let row = y < 7 ? y + 2 : y + 3;
            if (playerIndex) {
                column = 18 - column;
                row = 18 - row;
            }
            const side =
                x < 7 && y > 6 ? 1 :
                x > 6 && y < 7 ? 2 :
                                 0;

            spaces.push(`<div id="${className}-${x}-${y}" class="${className}"
                data-x="${x}" data-y="${y}" data-side="${side}" 
                style="--column: ${column}; --row: ${row};"></div>`);
        }
    }

    if (playerIndex) {
        spaces.reverse();
    }

    return `<div id="pk-board">
        <div id="pk-board-shadow-container">
            <div id="pk-board-shadow"></div>
        </div>        
        <div id="pk-board-background"></div>
        <div id="pk-board-spaces">${spaces.join("")}</div>        
    </div>`;
}

function createStack(className, playerIndex, type, invert) {
    invert = invert ? "invert" : "";
    return `<div id="${className}-${playerIndex}-${type}" class="${className} ${invert}" data-player="${playerIndex}" data-type="${type}">
    </div>`;
}


function createHand(playerIndex, type, invert) {
    return createStack("pk-hand", playerIndex, type, invert);
}

function createReserve(playerIndex, type, invert) {
    return createStack("pk-reserve", playerIndex, type, invert);
}


function findSpace(x, y) {
    return document.getElementById(`pk-board-space-${x}-${y}`);
}

function findPiece(id) {
    return document.getElementById(`pk-piece-${id}`);
}

function findSelectedPiece() {
    return document.querySelector(".pk-piece.pk-selected");
}

function findLastMovedPiece(playerIndex) {
    return document.querySelector(`.pk-piece.last-moved[data-player="${playerIndex}"]`);
}

function findHole(x, y) {
    return document.getElementById(`pk-board-hole-${x}-${y}`);
}

function findHand(playerIndex, type) {
    return document.getElementById(`pk-hand-${playerIndex}-${type}`)
}

function findReserve(playerIndex, type) {
    return document.getElementById(`pk-reserve-${playerIndex}-${type}`)
}

function getLocalCoords(x, y, angle) {
    const direction = Directions[intMod(parseInt(angle), 4)];

    return {
        dx: -direction.x * y - direction.y * x,
        dy: direction.x * x - direction.y * y,
    }
}

function getField(field, spaceX, spaceY, ignores = []) {
    const pieces = [];
    for (let y = spaceY - 4; y <= spaceY + 4; ++y) {
        for (let x = spaceX - 4; x <= spaceX + 4; ++x) {
            if (ignores.some(ignore => ignore.x === x && ignore.y === y)) {
                continue;
            }

            const space = findSpace(x, y) || findHole(x, y);
            const piece = space && space.querySelector(".pk-piece");
            if (piece) {
                pieces.push(piece);
            }
        }
    }

    const threat = [0, 0];

    for (const piece of pieces) {
        const playerIndex = parseInt(piece.dataset.player);
        const type = parseInt(piece.dataset.type);
        const pieceX = parseInt(piece.parentElement.dataset.x);
        const pieceY = parseInt(piece.parentElement.dataset.y);

        for (const [x, y] of field[type]) {
            const {dx, dy} = getLocalCoords(x, y, getStyle(piece, {angle: null}).angle);

            if (pieceX + dx === spaceX && pieceY + dy === spaceY) {
                if (type === PieceType.fire) {
                    ++threat[1 - playerIndex];
                }
                ++threat[playerIndex];
            }
        }
    }

    return threat;
}

function getThreat(spaceX, spaceY, covered = false, ignores = []) {
    const threat = getField(PieceThreat, spaceX, spaceY, ignores);
    if (covered) {
        const cover = getField(PieceCover, spaceX, spaceY, ignores);
        for (const index of range(2)) {
            const isBase = index ? spaceX > 6 && spaceY < 7 : spaceX < 7 && spaceY > 6;
            const coverValue = Math.sign(cover[index] + (isBase ? 1 : 0));
            threat[1 - index] = Math.max(0, threat[1 - index] - coverValue);
        }
    }
    return threat;
}

function checkCaptures(playerIndex, sourceX, sourceY, angle, offsets, additionalThreat, ignores) {
    for (const [x, y] of offsets) {
        const {dx, dy} = getLocalCoords(x, y, angle);
        const spaceX = sourceX + dx;
        const spaceY = sourceY + dy;

        if (ignores.every(({x, y}) => x !== spaceX || y !== spaceY)) {
            const space = findSpace(spaceX, spaceY) || findHole(spaceX, spaceY);
            if (space && space.querySelector(`.pk-piece[data-player="${playerIndex}"]`)) {
                const threat = getThreat(spaceX, spaceY, true, ignores);
                if (threat[1 - playerIndex] + additionalThreat >= 2) {
                    return true;
                }
            }
        }
    }
    return false;
}

function getValidAngles(playerIndex, piece, spaceX, spaceY, coveredCoords) {
    if (piece === PieceType.fire) {
        return range(4).filter(angle =>
            !checkCaptures(playerIndex, spaceX, spaceY, angle, PieceThreat[piece], 1, [{x: spaceX, y: spaceY}]));
    } else {
        return range(4).filter(angle =>
            coveredCoords.every(({coverX, coverY}) =>
                PieceCover[piece].some(([x, y]) => {
                    const {dx, dy} = getLocalCoords(x, y, angle);
                    return spaceX + dx === coverX && spaceY + dy === coverY;
                })));
    }
}

function prepareDeploy(playerIndex, piece, redeploySource = null) {
    const query = piece === PieceType.lotus ?
        ".pk-board-space:empty, .pk-board-hole:empty" :
        ".pk-board-space:empty"
    const spaces = Array.from(document.querySelectorAll(query));
    const result = [];

    const coveredCoords = redeploySource ?
        getCoveredCoords(playerIndex, piece, redeploySource.x, redeploySource.y, redeploySource.angle) :
        [];

    for (const space of spaces) {
        const spaceX = parseInt(space.dataset.x);
        const spaceY = parseInt(space.dataset.y);
        const threat = getThreat(spaceX, spaceY);
        const isBase = playerIndex ? spaceX > 6 && spaceY < 7 : spaceX < 7 && spaceY > 6;

        const isValid = piece === PieceType.lotus ?
            threat[1 - playerIndex] <= 2 :
            threat[1 - playerIndex] === 0
            && (isBase || threat[playerIndex] > 0);

        if (isValid) {
            const angles = getValidAngles(playerIndex, piece, spaceX, spaceY, coveredCoords);
            if (angles.length > 0) {
                result.push({space, angles});
            }
        }
    }
    return result;
}

class Queue {
    values = [];
    offset = 0;

    isEmpty() { return this.offset === this.values.length; }
    enqueue(value) { this.values.push(value); }
    dequeue() { return this.isEmpty() ? undefined : this.values[this.offset++]; }
}

function *spacesAround(space) {
    let index = 0;
    for (const direction of Directions) {
        const {x, y} = direction;
        yield [index++, {x: space.x + x, y: space.y + y}];
    }
}

function snapAngle(angle, angles) {
    const steps = angles.map(a => [intMod(a - angle, 4), a]);
    steps.sort();
    const newAngle = steps[0][1];
    const angleDiff = intMod(newAngle - angle + 2, 4) - 2;
    return angle + angleDiff;
}

function* collectPaths(x, y, range) {
    const queue = new Queue;
    const visited = new Set;

    const startPath = {
        space: {x, y},
        steps: []
    };

    queue.enqueue(startPath);
    visited.add(JSON.stringify(startPath.space));

    while (!queue.isEmpty()) {
        const {space, steps} = queue.dequeue();
        if (steps.length < range) {
            for (const [directionIndex, newSpace] of spacesAround(space)) {
                const value = JSON.stringify(newSpace);

                if (!visited.has(value)) {
                    visited.add(value);
                    const newPath = {
                        space: newSpace,
                        steps: [...steps, directionIndex]
                    };
                    const isPassable = yield newPath;
                    if (isPassable === undefined || isPassable) {
                        queue.enqueue(newPath);
                    }
                }
            }
        }
    }
}

function getCoveredCoords(playerIndex, pieceType, sourceX, sourceY, angle) {
    if (PieceCover[pieceType].length === 0)
    {
        return [];
    }

    const covered = [];
    for (let y = sourceY - 2; y <= sourceY + 2; ++y) {
        for (let x = sourceX - 2; x <= sourceX + 2; ++x) {
            if (x === sourceX && y === sourceY) {
                continue;
            }

            const space = findSpace(x, y);
            const piece = space && space.querySelector(".pk-piece");
            const cover = piece && PieceType[piece.dataset.type];

            if (cover && cover.length > 0) {
                const angle = getStyle(piece, {angle: null}).angle;

                for (const shift of cover) {
                    const {dx, dy} = getLocalCoords(shift[0], shift[1], angle);
                    const key = JSON.stringify({
                        x: x + dx,
                        y: y + dy
                    });
                    covered[key] = true;
                }
            }
        }
    }

    const source = [{x: sourceX, y: sourceY}];
    return PieceCover[pieceType].map(([x, y]) => {
        const {dx, dy} = getLocalCoords(x, y, angle);
        const spaceX = sourceX + dx;
        const spaceY = sourceY + dy;

        const isBase = playerIndex ? spaceX > 6 && spaceY < 7 : spaceX < 7 && spaceY > 6;
        if (isBase || covered[JSON.stringify({x: spaceX, y: spaceY})]) {
            return;
        }

        const piece = findSpace(spaceX, spaceY).querySelector(`.pk-piece[data-player="${playerIndex}"]`);
        if (piece && getThreat(spaceX, spaceY, true, source)[1 - playerIndex] >= 2) {
            return {coverX: spaceX, coverY: spaceY};
        }
    }).filter(coords => coords);
}

function prepareMove(playerIndex, piece, sourceX, sourceY, angle) {
    const movementRange =
        piece === PieceType.lotus || piece === PieceType.air ? 0 :
        piece === PieceType.earth ? 1 : 2;
    const source =[{x: sourceX, y: sourceY}];
    const result = [];

    const coveredCoords = getCoveredCoords(playerIndex, piece, sourceX, sourceY, angle);

    const paths = collectPaths(sourceX, sourceY, movementRange);
    let item = paths.next();

    while (!item.done) {
        const path = item.value;
        const space = findSpace(path.space.x, path.space.y);

        if (space) {
            const x = parseInt(space.dataset.x);
            const y = parseInt(space.dataset.y);
            const threat = getThreat(x, y, true, source);
            const threatened = threat[1 - playerIndex] >= (piece === PieceType.fire ? 1 : 2);

            const isPassable = !threatened && space && space.querySelector(".pk-piece") === null;
            const angles = getValidAngles(playerIndex, piece, x, y, coveredCoords);

            if (isPassable && angles.length > 0) {
                result.push({...path, space, angles});
            }
            item = paths.next(isPassable);
        } else {
            item = paths.next(false);
        }
    }

    return result;
}

function getCoordsPieces(bits) {
    const result = [];
    while (bits !== 0) {
        const x = bits & 0xF;
        const y = bits >>> 4 & 0xF;
        result.push((findSpace(x, y) || findSpace(x, y)).querySelector(".pk-piece"));
        bits >>>= 8;
    }
    return result;
}

const Paiko = {
    constructor() {
        console.log(`${gameName} constructor`);
        this.playerIndex = null;
    },

    setup(data) {
        console.log("Starting game setup");

        const players = data.players;
        this.playerIndex = this.isSpectator ?
            0 : parseInt(players[this.player_id].no) - 1;
        const playerIds = Object.keys(players);

        const container = createElement(
            document.getElementById('game_play_area'),
            `<div id="pk-container" data-player="${this.playerIndex}"></div>`);

        createElement(container, createBoard(this.playerIndex));
        const boardSpaces = document.getElementById("pk-board-spaces");

        for (const playerId of playerIds) {
            const player = players[playerId];
            player.index = parseInt(player.no) - 1;

            const types = Object.keys(PieceType);
            if ((player.index !== this.playerIndex)) {
                types.reverse();
            }

            for (const type of types) {
                createElement(boardSpaces, createHand(player.index, PieceType[type], this.playerIndex === 1));

                createElement(boardSpaces, createReserve(player.index, PieceType[type], this.playerIndex === 1));
            }

            const pieces = data.pieces.filter(piece => parseInt(piece.player) === player.index && parseInt(piece.status) !== PieceStatus.board);

            for (const piece of pieces) {
                const parent =
                    parseInt(piece.status) === PieceStatus.hand ?
                        findHand(player.index, piece.type) :
                        findReserve(player.index, piece.type);
                this.addPiece(piece.id, parent, player.index, parseInt(piece.type), player.index * 2);
            }
        }

        for (const space of document.querySelectorAll(".pk-board-space, .pk-board-hole")) {
            space.addEventListener("mousedown", (event) => {
                this.onSpaceClicked(space);
            });
        }

        for (const {player, id, x, y, type, angle, status} of data.pieces) {
            if (parseInt(status) === PieceStatus.board) {
                this.addPiece(id, findSpace(x, y) || findHole(x, y), parseInt(player), type, parseInt(angle));
            }
        }

        for (const {id, moves} of data.lastMoves) {
            const piece = findPiece(id);
            if (piece) {
                piece.classList.add("last-moved");
                piece.dataset.moves = moves.toString();
            }
        }

        this.bgaSetupPromiseNotifications({
            logger: console.log,
            prefix: "onNotification"
        });

        console.log("Ending game setup");
    },

    addPiece(id, parent, playerIndex, type, angle = 0) {
        const piece = createElement(parent, createPiece(id, playerIndex, type, angle));
        piece.addEventListener("mousedown", event => {
            event.stopPropagation();
            this.onPieceClick(piece);
        });
    },

    onEnteringState(stateName, state) {
        console.log(`Entering state: ${stateName}`);

        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case State.draft:
                case State.reserve:
                case State.clientDraft: {
                    clearTag("pk-selectable");
                    clearTag("pk-selected");
                    const index = stateName === State.reserve ? 1 - this.playerIndex : this.playerIndex
                    const pieces = document.querySelectorAll(`.pk-reserve .pk-piece[data-player="${index}"]`);
                    for (const piece of pieces) {
                        piece.classList.add("pk-selectable");
                    }
                    break;
                }
                case State.action: {
                    const pieces = document.querySelectorAll(`.pk-hand[data-player="${this.playerIndex}"] .pk-piece:last-child, .pk-board-space .pk-piece[data-player="${this.playerIndex}"]`);

                    for (const piece of pieces) {
                        const type = parseInt(piece.dataset.type);
                        if (piece.closest(".pk-hand") !== null || type !== PieceType.lotus && type !== PieceType.air) {
                            piece.classList.add("pk-selectable");
                        }
                    }
                    break;
                }
                case State.saiMove: {
                    clearTag("pk-selectable");
                    const piece = findPiece(state.args.saiId);
                    piece.classList.add("pk-selectable", "pk-selected");
                    this.setClientState(State.clientMove, {
                        descriptionmyturn: "${you} must shift ${pieceIcon}",
                        args: {
                            sourceSpace: piece.parentElement,
                            selectedPiece: piece,
                            pieceIcon: `${this.playerIndex},${piece.dataset.id}`,
                            canSkip: true
                        },
                        possibleactions: [Action.clientMove, Action.skip]
                    });
                    break;
                }
                case State.clientDeploy: {
                    const type = parseInt(state.args.selectedPiece.dataset.type);
                    this.paths = prepareDeploy(this.playerIndex, type);
                    for (const path of this.paths) {
                        path.space.classList.add("pk-selectable");
                    }
                    break;
                }
                case State.clientMove: {
                    const selectedPiece = state.args.selectedPiece;
                    const type = parseInt(selectedPiece.dataset.type);
                    const angle = getStyle(selectedPiece, {angle: null}).angle;
                    const x = parseInt(selectedPiece.parentElement.dataset.x);
                    const y = parseInt(selectedPiece.parentElement.dataset.y);

                    this.paths = prepareMove(this.playerIndex, type, x, y, angle);

                    if (type === PieceType.water) {
                        this.paths.push(...prepareDeploy(this.playerIndex, type, {x, y, angle}));
                    }

                    for (const {space} of this.paths) {
                        space.classList.add("pk-selectable");
                    }

                    break;
                }
                case State.clientConfirm: {
                    state.args.selectedPiece.classList.add("pk-selected");
                    break;
                }
            }
        }
    },

    onLeavingState(stateName) {
        console.log(`Leaving state: ${stateName}`);

        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case State.draft:
                case State.clientDraft: {
                    clearTag("pk-selectable");
                    clearTag("pk-selected");
                    break;
                }
                case State.action: {
                    break;
                }
                case State.clientDeploy:
                case State.clientMove: {
                    clearTag("pk-selectable", " .pk-board-space,  .pk-board-hole");
                    clearTag("pk-selected");
                    break
                }
            }
        }
    },

    onUpdateActionButtons(stateName, args) {
        console.log(`onUpdateActionButtons: ${stateName}`, args);

        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case State.draft:
                case State.clientDraft:
                case State.reserve: {
                    this.addActionButton("pk-confirm-button", _("Confirm"), () => {
                        const ids = Array.from(document.querySelectorAll(".pk-hand .pk-piece.pk-selectable"))
                            .map(piece => piece.dataset.id)
                            .join(",");
                        this.bgaPerformAction(Action.draft, {ids});
                    });
                    document.getElementById("pk-confirm-button").classList.add("disabled");
                    break;
                }
                case State.action: {
                    const pieces = document.querySelectorAll(`.pk-reserve .pk-piece[data-player="${this.playerIndex}"]`);
                    if (pieces.length > 0) {
                        this.addActionButton("pk-draft-button", _("Draw new tiles"), () => {
                            this.setClientState(State.clientDraft, {
                                "descriptionmyturn": _("${you} must draw ${count} tile(s) from reserve"),
                                possibleactions: [Action.draft],
                                args: {count: 3}
                            });
                        });
                    }
                    break;
                }
                case State.clientConfirm: {
                    this.addActionButton("pk-confirm-button", _("Confirm"), () => this.confirmAction(stateName, args));
                    break;
                }
            }

            const cancellableStates = [
                State.clientDraft,
                State.clientDeploy,
                State.clientConfirm];
            if (cancellableStates.indexOf(stateName) >= 0) {
                this.addActionButton("pk-cancel", _("Cancel"), () => this.cancelAction(stateName, args), null, null, "gray");
            }

            if (args && args.canSkip) {
                this.addActionButton("pk-skip", _("Skip"), () => {
                    this.cancelAction(stateName, args);
                    this.bgaPerformAction(Action.skip);
                }, null, null, "red");
            }
        } else if (!this.isSpectator) {
            switch (stateName) {

            }
        }
    },

    async cancelAction(stateName, args) {
        if (stateName === State.clientDraft) {
            const drafted = document.querySelectorAll(".pk-hand .pk-piece.pk-selectable");
            for (const piece of drafted) {
                this.animateMovePiece(piece, findReserve(this.playerIndex, piece.dataset.type));
                await this.wait(120);
            }
        } else if (stateName === State.clientMove) {
            this.animateMovePiece(args.selectedPiece, args.sourceSpace);
        } else if (stateName === State.clientConfirm) {
            const piece = findSelectedPiece();
            piece.classList.remove("pk-selected");

            const actions = this.gamedatas.gamestate.possibleactions;
            if (actions.indexOf(Action.move) >= 0) {
                this.animateMovePiece(piece, args.sourceSpace, args.angle);
            } else if (actions.indexOf(Action.deploy) >= 0) {
                this.animateMovePiece(piece, findHand(this.playerIndex, piece.dataset.type), args.angle);
            }
        }
        this.restoreServerGameState()
    },

    cancelOtherActions(currentPiece) {
        const selectedPiece = findSelectedPiece();
        if (selectedPiece && selectedPiece !== currentPiece) {
            const state = this.gamedatas.gamestate;
            this.cancelAction(state.name, state.args);
        }
    },

    confirmAction(stateName, args) {
        const {selectedPiece, targetSpace} = args
        if (this.checkAction(Action.move, true)) {
            const {sourceSpace} = args;
            const type = parseInt(selectedPiece.dataset.type);
            const path = this.paths.find(path => path.space === targetSpace);

            if (!path.steps) {
                const sourceSpace = selectedPiece.parentElement;
                this.bgaPerformAction(Action.deploy, {
                    id: selectedPiece.dataset.id,
                    x: targetSpace.dataset.x,
                    y: targetSpace.dataset.y,
                    fromX: sourceSpace.dataset.x,
                    fromY: sourceSpace.dataset.y,
                    type: type,
                    waterRedeploy: true,
                    angle: intMod(getStyle(selectedPiece, {angle: null}).angle, 4)
                })
            } else {
                this.bgaPerformAction(Action.move, {
                    id: selectedPiece.dataset.id,
                    x: sourceSpace.dataset.x,
                    y: sourceSpace.dataset.y,
                    steps: path.steps.join(","),
                    type: type,
                    angle: intMod(getStyle(selectedPiece, {angle: null}).angle, 4)
                });
            }
        } else {
            this.bgaPerformAction(Action.deploy, {
                id: selectedPiece.dataset.id,
                type: selectedPiece.dataset.type,
                x: parseInt(targetSpace.dataset.x),
                y: parseInt(targetSpace.dataset.y),
                angle: intMod(getStyle(selectedPiece, {angle: null}).angle, 4)
            });
        }
    },

    waitForAnimation(element, name) {
        return new Promise(resolve => {
            function check(event) {
                if (event.animationName === name) {
                    resolve()
                } else {
                    element.addEventListener('animationend', check, {once: true});
                }
            }
            element.addEventListener('animationend', check, {once: true});
        });
    },

    async animateHighlightSpace(space) {
        space.classList.add("highlight");
        await this.waitForAnimation(space, "pk-highlight");
        space.classList.remove("highlight");
    },

    async animateMovePiece(piece, target, angle = null) {
        const srcRect = piece.getBoundingClientRect();
        target.appendChild(piece);
        if (this.bgaAnimationsActive()) {
            if (angle !== null) {
                setStyle(piece, {angle});
            }
        } else {
            if (angle !== null) {
                setStyle(piece, {angle});
            }
        }
    },

    onSpaceClicked(space) {
        console.log("clicked", space);
        if (space.classList.contains("pk-selectable")) {
            const state = this.gamedatas.gamestate;
            const piece = state.args && state.args.selectedPiece;
            const angle = piece && getStyle(piece, {angle: null}).angle;

            if (this.checkAction(Action.deploy, true)) {
                const path = this.paths.find(path => path.space === space);
                this.animateMovePiece(piece, space,
                    snapAngle(angle, path.angles));
                this.setClientState(State.clientConfirm, {
                    descriptionmyturn: _("${you} must rotate and confirm the deployment of ${pieceIcon}"),
                    args: {
                        selectedPiece: piece,
                        targetSpace: space,
                        angle: getStyle(piece, {angle: null}).angle,
                        x: parseInt(space.dataset.x),
                        y: parseInt(space.dataset.y),
                        pieceIcon: `${this.playerIndex},${piece.dataset.id}`,
                    },
                    possibleactions: [Action.deploy]
                })
            } else if (this.checkAction(Action.clientMove, true)) {
                const piece = this.gamedatas.gamestate.args.selectedPiece;
                const source = piece.parentElement;

                const path = this.paths.find(path => path.space === space);
                this.animateMovePiece(piece, space,
                    path ?  snapAngle(angle, path.angles) : null);

                const actions = [Action.move, Action.deploy];
                if (this.checkAction(Action.skip, true)) {
                    actions.push(Action.skip);
                }

                this.setClientState(State.clientConfirm, {
                    descriptionmyturn: _("${you} must rotate and confirm the shift of ${pieceIcon}"),
                    args: {
                        selectedPiece: piece,
                        sourceSpace: source,
                        targetSpace: space,
                        angle: getStyle(piece, {angle: null}).angle,
                        x: parseInt(space.dataset.x),
                        y: parseInt(space.dataset.y),
                        pieceIcon: `${this.playerIndex},${piece.dataset.id}`,
                        canSkip: this.gamedatas.gamestate.args && this.gamedatas.gamestate.args.canSkip
                    },
                    possibleactions: actions
                })
            }
        }
    },

    onPieceClick(piece) {
        console.log("clicked", piece);
        if (piece.classList.contains("pk-selectable")) {
            const state = this.gamedatas.gamestate;
            const type = parseInt(piece.dataset.type);

            if (this.checkAction(Action.draft, true) && state.name !== State.action) {
                const index = state.name === State.reserve ? 1 - this.playerIndex : this.playerIndex
                const hand = findHand(index, type);

                const count = (state.args && state.args.count) || 1;
                let drafted = document.querySelectorAll(".pk-hand .pk-piece.pk-selectable").length;

                if (piece.parentElement !== hand) {
                    if (drafted < count) {
                        ++drafted;
                        this.animateMovePiece(piece, hand);
                        if (drafted === count) {
                            this.statusBar.setTitle(
                                _("${you} must confirm the drawn tiles"));
                        } else {
                            this.statusBar.setTitle(
                                _("${you} must draw ${count} tile(s) from reserve"),
                                {count: count - drafted});
                        }
                    }
                } else {
                    --drafted;
                    this.animateMovePiece(piece, findReserve(index, type));
                    if (state.name === State.reserve) {
                        this.statusBar.setTitle(
                            _("${you} must choose a piece from reserve for the opponent to draw"),
                            {count: count - drafted});

                    } else {
                        this.statusBar.setTitle(
                            _("${you} must draw ${count} tile(s) from reserve"),
                            {count: count - drafted});
                    }
                }

                const remaining = document.querySelectorAll(".pk-reserve .pk-piece.pk-selectable").length;

                document.getElementById("pk-confirm-button").classList.toggle("disabled", remaining !== 0 && drafted !== count);
            } else if (piece.closest(".pk-board-space, .pk-board-hole") === null) {
                this.cancelAction(state.name, state.args);
                this.setClientState(State.clientDeploy, {
                    descriptionmyturn: _("${you} must select the space to deploy ${pieceIcon}"),
                    args: {
                        selectedPiece: piece,
                        pieceIcon: `${this.playerIndex},${piece.dataset.id}`
                    },
                    possibleactions: [Action.deploy]
                });
                piece.classList.add("pk-selected");
            } else if (piece.classList.contains("pk-selected") && state.name === State.clientConfirm) {
                const {angle} = getStyle(piece, {angle: null});
                const path = this.paths.find(path => path.space === piece.parentElement);
                if (path) {
                    setStyle(piece, {
                        angle: path ?
                            snapAngle(angle + 1, path.angles) :
                            angle + 1
                    });
                }
            } else if ([State.action, State.clientDeploy, State.clientMove, State.clientConfirm].indexOf(state.name) >= 0) {
                this.cancelAction(state.name, state.args);
                clearTag("pk-selected");
                this.setClientState(State.clientMove, {
                    descriptionmyturn: "${you} must shift ${pieceIcon}",
                    args: {
                        sourceSpace: piece.parentElement,
                        selectedPiece: piece,
                        pieceIcon: `${this.playerIndex},${piece.dataset.id}`
                    },
                    possibleactions: [Action.clientMove]
                });
                piece.classList.add("pk-selected");
            }
        }
    },

    async onNotificationMove({playerId, id, x, y, angle, score, isMove}) {
        const playerIndex = this.gamedatas.players[playerId].index;
        const piece = findPiece(id);
        this.cancelOtherActions(piece);
        clearTag("pk-selected");
        clearTag("pk-selectable");

        const lastPiece = findLastMovedPiece(playerIndex);
        if (lastPiece) {
            if (isMove && lastPiece === piece) {
                lastPiece.dataset.moves = parseInt(lastPiece.dataset.moves) + 1;
            } else {
                lastPiece.classList.remove("last-moved");
            }
        }

        if (isMove && piece !== lastPiece) {
            piece.classList.add("last-moved");
            piece.dataset.moves = "1";
        }

        const space = findSpace(x, y) || findHole(x, y);
        if (piece.parentElement !== space || getStyle(piece, {angle: null}.angle !== angle)) {
            await this.animateMovePiece(piece, space, angle)
        }

        if (score !== 0) {
            this.scoreCtrl[playerId].incValue(score);
        }
    },

    async onNotificationThreat({threats}) {
        const highlights = [];
        for (const [x, y] of threats) {
            const space = findSpace(x, y) || findHole(x, y);
            highlights.push(this.animateHighlightSpace(space));
        }
        await Promise.all(highlights);
    },

    async onNotificationCapture({playerIndex, id, score}) {
        findPiece(id).remove();
        if (score !== 0) {
            const playerId = Object.keys(this.gamedatas.players).find(playerId =>
                this.gamedatas.players[playerId].index === playerIndex);
            this.scoreCtrl[playerId].incValue(score);
        }
    },

    async onNotificationDraft({playerIndex, pieceIds}) {
        const lastPiece = findLastMovedPiece(playerIndex);
        if (lastPiece) {
            lastPiece.classList.remove("last-moved");
        }

        for (const id of pieceIds) {
            const piece = document.getElementById(`pk-piece-${id}`);
            const hand = findHand(playerIndex, piece.dataset.type);
            await this.animateMovePiece(piece, hand);
        }
    },

    formatPiece(playerIndex, ...ids) {
        const result = [];
        for (const id of ids) {
            const type = Math.floor(parseInt(id) / 3) % 8;
            result.push(createPiece(null, playerIndex, type));
        }
        return result.join("");
    },

    format_string_recursive(log, args) {
        if (args && !("substitutionComplete" in args)) {
            args.substitutionComplete = true;
            const formatters = {
                'piece': this.formatPiece,
            };
            for (const iconType of Object.keys(formatters)) {
                const icons = Object.keys(args).filter(name => name.startsWith(`${iconType}Icon`));

                for (const icon of icons) {
                    const values = args[icon].toString().split(",");
                    args[icon] = formatters[iconType].call(this, ...values);
                }
            }
        }
        return this.inherited({callee: this.format_string_recursive}, arguments);
    }
}

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter"
], (dojo, declare) => declare(`bgagame.${gameName}`, ebg.core.gamegui, Paiko));