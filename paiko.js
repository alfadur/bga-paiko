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
    action: "action",
    saiMove: "saiMove",
    capture: "capture",
    clientDeploy: "clientDeploy",
    clientMove: "clientMove",
    clientConfirm: "clientConfirm"
}
Object.freeze(State);

const Action = {
    move: "actMove",
    deploy: "actDeploy",
    skip: "actSkip",
    capture: "capture",
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

const PieceThreat = [
    [[0, -1]],
    [[-1, -1], [0, -1], [1, -1], [-1, 0], [1, 0], [-1, 1], [0, 1], [1, 1]],
    [[0, -2], [0, -3], [0, -4]],
    [],
    [[-1, -2], [1, -2], [-2, -1], [2, -1], [-2, 1], [2, 1], [-1, 2], [1, 2]],
    [[-2, -2], [-1, -2], [0, -2], [1, -2], [2, -2], [-1, -1], [0, -1], [1, -1]],
    [[0, -2], [0, -1], [-2, 0], [-1, 0], [1, 0], [2, 0], [0, 1], [0, 2]],
    [[-1, -1], [1, -1], [-1, 1], [1, 1]]
];
Object.freeze(PieceThreat);

const Directions = [[0, -1], [1, 0], [0, 1], [-1, 0]]
    .map(([x, y]) => ({x, y}));
Object.freeze(Directions);

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

function createPiece(playerIndex, type, angle = 0) {
    const spriteX = type % 4;
    const spriteY = type >> 2;
    return `<div class="pk-piece" style="--angle: ${angle}; --sprite-x: ${spriteX}; --sprite-y: ${spriteY}" 
        data-type="${type}" data-player="${playerIndex}">
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

            let column = x < 7 ? x + 1 : x + 2;
            let row = y < 7 ? y + 1 : y + 2;
            if (playerIndex) {
                column = 16 - column;
                row = 16 - row;
            }
            const side =
                x < 7 && y > 6 ? 1 :
                x > 6 && y < 7 ? 2 :
                                 0;

            spaces.push(`<div id="pk-board-space-${x}-${y}" class="${className}"
                data-x="${x}" data-y="${y}" data-side="${side}" 
                style="--column: ${column}; --row: ${row};"></div>`);
        }
    }
    return `<div id="pk-board">
        <div id="pk-board-shadow-container">
            <div id="pk-board-shadow"></div>
        </div>        
        <div id="pk-board-background"></div>
        <div id="pk-board-spaces">${spaces.join("")}</div>        
    </div>`;
}

function createHand(playerIndex, type, invert) {
    invert = invert ? "invert" : "";
    return `<div id="pk-hand-${playerIndex}-${type}" class="pk-hand ${invert}" data-player="${playerIndex}" data-type="${type}">
    </div>`;
}

function findSpace(x, y) {
    return document.getElementById(`pk-board-space-${x}-${y}`);
}

function findHole(x, y) {
    return document.getElementById(`pk-board-hole-${x}-${y}`);
}

function findHand(playerIndex, type) {
    return document.getElementById(`pk-hand-${playerIndex}-${type}`)
}

function getPath(fromX, fromY, toX, toY, orthogonal = false) {
    const path = [];
    while (toX > fromX) {
        ++fromX;
        path.push(1);
    }
    while (toX < fromX) {
        --fromX;
        path.push(3)
    }

    if (orthogonal && path.length > 0 && toY !== fromY) {
        return null;
    }

    while (toY > fromY) {
        ++fromY;
        path.push(2);
    }
    while (toY < fromY) {
        --fromY;
        path.push(0)
    }

    return path;
}

function getThreat(spaceX, spaceY) {
    const pieces = [];
    for (let y = spaceY - 4; y <= spaceY + 4; ++y) {
        for (let x = spaceX - 4; x <= spaceX + 4; ++x) {
            const piece = document.querySelector(`#pk-board-space-${x}-${y} .pk-piece`);
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
        const angle = getStyle(piece, {angle: null}).angle;
        const direction = Directions[intMod(angle, 4)];

        for (const [x, y] of  PieceThreat[type]) {
            const {dx, dy} = {
                dx: -direction.x * y - direction.y * x,
                dy: direction.x * x - direction.y * y,
            }

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

function prepareDeploy(playerIndex, piece) {
    const query = piece === PieceType.lotus ?
        ".pk-board-space:empty, .pk-board-hole:empty" :
        ".pk-board-space:empty"
    const spaces = Array.from(document.querySelectorAll(query));
    const result = [];

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
            result.push(space);
        }
    }
    return result;
}

function prepareMove(piece) {
    return document.querySelectorAll(".pk-board-space:empty")
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

        const board = createElement(container, createBoard(this.playerIndex));
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
            }

            const pieces = data.pieces.filter(piece => piece.player_id === playerId && piece.x === null);

            for (const piece of pieces) {
                const hand = findHand(player.index, piece.type);
                this.addPiece(hand, player.index, parseInt(piece.type), player.index * 2);
            }
        }

        for (const space of document.querySelectorAll(".pk-board-space, .pk-board-hole")) {
            space.addEventListener("mousedown", (event) => {
                this.onSpaceClicked(space);
            });
        }

        for (const {player_id: playerId, x, y, type, angle, status} of data.pieces) {
            if (x !== null) {
                this.addPiece(findSpace(x, y), players[playerId].index, type, angle);
            }
        }

        this.bgaSetupPromiseNotifications({
            logger: console.log,
            prefix: "onNotification"
        });

        console.log("Ending game setup");
    },

    onEnteringState(stateName, state) {
        console.log(`Entering state: ${stateName}`);

        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case State.action: {
                    const pieces = document.querySelectorAll(`.pk-hand[data-player="${this.playerIndex}"] .pk-piece, .pk-board-space .pk-piece[data-player="${this.playerIndex}"]`);
                    for (const piece of pieces) {
                        piece.classList.add("pk-selectable");
                    }
                    break;
                }
                case State.saiMove: {
                    clearTag("pk-selectable");
                    const piece = findSpace(state.args.x, state.args.y)
                        .querySelector(".pk-piece");
                    piece.classList.add("pk-selected");
                    this.setClientState(State.clientMove, {
                        descriptionmyturn: "${you} must move ${pieceIcon}",
                        args: {
                            selectedPiece: piece,
                            pieceIcon: `${this.playerIndex},${piece.dataset.type}`,
                            canSkip: true
                        },
                        possibleactions: [Action.clientMove, Action.skip]
                    });
                    break;
                }
                case State.capture: {
                    const captures = state.args.captures || state.args.fireCaptures;
                    const pieces = getCoordsPieces(captures);
                    for (const piece of pieces) {
                        piece.classList.add("pk-selectable");
                    }
                    break;
                }
                case State.clientDeploy: {
                    const type = parseInt(state.args.selectedPiece.dataset.type);
                    const spaces = prepareDeploy(this.playerIndex, type);
                    for (const space of spaces) {
                        space.classList.add("pk-selectable");
                    }
                    break;
                }
                case State.clientMove: {
                    const spaces = prepareMove(state.args.selectedPiece);
                    for (const space of spaces) {
                        space.classList.add("pk-selectable");
                    }

                    if (!this.checkAction(Action.skip, true)) {
                        const pieces = document.querySelectorAll(".pk-board-space .pk-piece");
                        for (const piece of pieces) {
                            piece.classList.add("pk-selectable");
                        }
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

    addPiece(parent, playerIndex, type, angle = 0) {
        const piece = createElement(parent, createPiece(playerIndex, type, angle));
        piece.addEventListener("mousedown", event => {
            event.stopPropagation();
            this.onPieceClick(piece);
        })
    },

    onLeavingState(stateName) {
        console.log(`Leaving state: ${stateName}`);

        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
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
                case State.clientConfirm: {
                    this.addActionButton("confirm-button", _("Confirm"), () => {
                        const {selectedPiece, sourceSpace, targetSpace} = args;
                        const type = parseInt(selectedPiece.dataset.type);

                        const path = getPath(
                            parseInt(sourceSpace.dataset.x),
                            parseInt(sourceSpace.dataset.y),
                            parseInt(targetSpace.dataset.x),
                            parseInt(targetSpace.dataset.y),
                            type === PieceType.water);
                        if (path === null) {
                            this.bgaPerformAction(Action.deploy, {
                                x: targetSpace.dataset.x,
                                y: targetSpace.dataset.y,
                                type: selectedPiece.dataset.type,
                                waterRedeploy: true,
                                angle: getStyle(selectedPiece, {angle: null}).angle
                            })
                        } else {
                            this.bgaPerformAction(Action.move, {
                                x: sourceSpace.dataset.x,
                                y: sourceSpace.dataset.y,
                                steps: getPath(
                                    parseInt(sourceSpace.dataset.x),
                                    parseInt(sourceSpace.dataset.y),
                                    parseInt(targetSpace.dataset.x),
                                    parseInt(targetSpace.dataset.y))
                                    .join(","),
                                type: selectedPiece.dataset.type,
                                angle: intMod(getStyle(selectedPiece, {angle: null}).angle, 4)
                            });

                        }
                    });
                    break;
                }
            }



            if (args?.canSkip) {
                this.addActionButton("pk-skip", _("Skip"), () => {
                    this.bgaPerformAction(Action.skip);
                }, null, null, "red");
            }
        } else if (!this.isSpectator) {
            switch (stateName) {

            }
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

    onSpaceClicked(space) {
        console.log("clicked", space);
        if (space.classList.contains("pk-selectable")) {
            if (this.checkAction(Action.deploy, true)) {
                const selectedPiece = this.gamedatas.gamestate.args.selectedPiece;
                this.bgaPerformAction(Action.deploy, {
                    type: this.gamedatas.gamestate.args.selectedPiece.dataset.type,
                    x: parseInt(space.dataset.x),
                    y: parseInt(space.dataset.y),
                    angle: getStyle(selectedPiece, {angle: null}).angle
                });
            } else if (this.checkAction(Action.clientMove, true)) {
                const piece = this.gamedatas.gamestate.args.selectedPiece;
                const source = piece.parentElement;
                space.appendChild(piece);

                const actions = [Action.move, Action.deploy];
                if (this.checkAction(Action.skip, true)) {
                    actions.push(Action.skip);
                }

                this.setClientState(State.clientConfirm, {
                    descriptionmyturn: _("${you} must confirm move with ${pieceIcon}"),
                    args: {
                        selectedPiece: piece,
                        sourceSpace: source,
                        targetSpace: space,
                        x: parseInt(space.dataset.x),
                        y: parseInt(space.dataset.y),
                        pieceIcon: `${this.playerIndex},${piece.dataset.type}`,
                        canSkip: this.gamedatas.gamestate.args?.canSkip
                    },
                    possibleactions: actions
                })
            }
        }
    },

    onPieceClick(piece) {
        console.log("clicked", piece);
        if (piece.classList.contains("pk-selectable")) {
            const space = piece.closest(".pk-board-space, .pk-board-hole");
            if (this.checkAction(Action.capture, true)) {
                this.bgaPerformAction(Action.capture, {
                    x: parseInt(space.dataset.x),
                    y: parseInt(space.dataset.y)
                })
            } else if (piece.closest(".pk-board-space, .pk-board-hole") === null) {
                this.setClientState(State.clientDeploy, {
                    descriptionmyturn: _("${you} must select the space to deploy ${pieceIcon}"),
                    args: {
                        selectedPiece: piece,
                        pieceIcon: `${this.playerIndex},${piece.dataset.type}`
                    },
                    possibleactions: [Action.deploy]
                });
                piece.classList.add("pk-selected");
            } else if (piece.classList.contains("pk-selected")) {
                if ([0, 2, 5].indexOf(parseInt(piece.dataset.type)) >= 0) {
                    const {angle} = getStyle(piece, {angle: null});
                    setStyle(piece, {angle: angle + 1});
                }
            } else {
                clearTag("pk-selected");
                this.setClientState(State.clientMove, {
                    descriptionmyturn: "${you} must move ${pieceIcon}",
                    args: {
                        selectedPiece: piece,
                        pieceIcon: `${this.playerIndex},${piece.dataset.type}`
                    },
                    possibleactions: [Action.clientMove]
                });
                piece.classList.add("pk-selected");
            }
        }
    },

    async onNotificationDeploy({playerId, type, x, y, angle, score}) {
        const playerIndex = this.gamedatas.players[playerId].index;
        const piece = parseInt(playerId) === this.player_id ?
            this.gamedatas.gamestate.args.selectedPiece :
            document.querySelector(`.pk-hand .pk-piece[data-type="${type}"][data-player="${playerIndex}"]`);
        if (piece) {
            findSpace(x, y).appendChild(piece);
            setStyle(piece, {angle});
        }
        if (score !== 0) {
            this.scoreCtrl[playerId].incValue(score);
        }
    },

    async onNotificationMove({playerId, from, to, angle, score}) {
        if (parseInt(playerId) !== this.player_id) {
            const piece = findSpace(from[0], from[1]).firstElementChild;
            const space = findSpace(to[0], to[1]);
            if (piece.parentElement !== space){
                space.appendChild(piece);
            }
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

    async onNotificationCapture({x, y}) {
        const piece = (findSpace(x, y) || findHole(x, y)).querySelector(".pk-piece");
        piece.remove();
    },

    formatPiece(playerIndex, type) {
        return createPiece(playerIndex, type);
    },

    format_string_recursive(log, args) {
        if (args && !("substitutionComplete" in args)) {
            args.substitutionComplete = true;
            const formatters = {
                'piece': this.formatPiece
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