{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- Paiko implementation : © <Your name here> <Your email address here>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
------->

<div id="paiko-tiles-reserve"></div>

<div id="paiko-board">
     <!-- BEGIN boardSquare -->
     <div class="paiko-board-square" style="left: {X}px; top: {Y}px"></div>
     <!-- END boardSquare -->
</div>

<script type="text/javascript">
     const jstpl_tile =
         `<div class="paiko-tile" data-type="\${type}"></div>`;
</script>

{OVERALL_GAME_FOOTER}
