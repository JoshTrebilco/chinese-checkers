<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] flex p-6 items-center min-h-screen flex-col">
        <svg id="board" 
        class="w-full h-auto max-w-[800px] max-h-[800px]"
        viewBox="0 0 800 800"
        preserveAspectRatio="xMidYMid meet"
        xmlns="http://www.w3.org/2000/svg"></svg>

   <script>
     const s = 30; // hex radius
     const svg = document.getElementById("board");

     function axialToPixel(q, r) {
       const x = s * Math.sqrt(3) * (q + r / 2);
       const y = s * (3 / 2) * r;
       return { x, y };
     }

     function hexPoints(cx, cy) {
       const pts = [];
       for (let i = 0; i < 6; i++) {
         const angle = (Math.PI / 180) * (60 * i - 30);
         const x = cx + s * Math.cos(angle);
         const y = cy + s * Math.sin(angle);
         pts.push(`${x},${y}`);
       }
       return pts.join(" ");
     }

     function drawHex(q, r, fill = "#e0f2f1") {
       const { x, y } = axialToPixel(q, r);
       const hex = document.createElementNS("http://www.w3.org/2000/svg", "polygon");
       hex.setAttribute("points", hexPoints(x + 400, y + 400));
       hex.setAttribute("fill", fill);
       hex.setAttribute("stroke", "#004d40");
       hex.setAttribute("stroke-width", "1.2");
       svg.appendChild(hex);

       // Add text label with coordinates
       const text = document.createElementNS("http://www.w3.org/2000/svg", "text");
       text.setAttribute("x", x + 400);
       text.setAttribute("y", y + 400);
       text.setAttribute("text-anchor", "middle");
       text.setAttribute("dominant-baseline", "middle");
       text.setAttribute("font-size", "16");
       text.setAttribute("fill", "#1b1b18");
       text.setAttribute("font-weight", "500");
       text.textContent = `${q},${r}`;
       svg.appendChild(text);
     }

     // Determine if a coordinate (q, r) is part of the Chinese Checkers star
     function isOnBoard(q, r) {
        const sum = -q-r;

        // --- Center hexagon (radius 4)
        if (Math.abs(q) <= 4 && Math.abs(r) <= 4 && Math.abs(sum) <= 4) return true;

        // --- Top (North) - r is primary coordinate (negative)
        if (r <= -5 && r >= -8 && q >= 1 && q <= 4 && sum >= 0 && sum <= 4) return true;

        // --- Bottom (South) - r is primary coordinate (positive)
        if (r >= 5 && r <= 8 && q >= -4 && q <= -1 && sum >= -4 && sum <= 0) return true;

        // --- Top-right (Northeast) - q is primary coordinate (positive)
        if (q >= 5 && q <= 8 && r >= -4 && r <= 0 && sum >= -4 && sum <= 0) return true;

        // --- Bottom-left (Southwest) - q is primary coordinate (negative)
        if (q <= -5 && q >= -8 && r >= 0 && r <= 4 && sum <= 4 && sum >= 0) return true;

        // --- Top-left (Northwest) - sum is primary coordinate (negative)
        if (sum <= -5 && sum >= -8 && r <= 4 && r >= -4 && q >= -4 && q <= 4) return true;

        // --- Bottom-right (Southeast) - sum is primary coordinate (positive)
        if (sum >= 5 && sum <= 8 && r <= 4 && r >= -4 && q >= -4 && q <= 4) return true;

        return false;
      }

     // Optional: color gradient by region
     function colorFor(q, r) {
       const sum = -q-r;
       if (r <= -5) return "#93c5fd";        // North - blue-00
       if (r >= 5) return "#fca5a5";         // South - red-300
       if (q >= 5) return "#fde68a";         // NE - yellow-300
       if (q <= -5) return "#86efac";        // SW - green-300
       if (sum <= -5)  return "#5eead4";     // SE - teal-300
       if (sum >= 5) return "#c4b5fd";       // NW - purple-300
       return "#fafafa";                     // center area
     }

     // Board state - will be populated from backend
     let boardState = {
       cells: {},
       players: {}
     };

     // Color mapping for tokens
     const colorMap = {
       blue: { fill: '#3b82f6', stroke: '#60a5fa', border: '#93c5fd' },
       red: { fill: '#ef4444', stroke: '#f87171', border: '#fca5a5' },
       yellow: { fill: '#eab308', stroke: '#fbbf24', border: '#fde68a' },
       green: { fill: '#22c55e', stroke: '#4ade80', border: '#86efac' },
       teal: { fill: '#14b8a6', stroke: '#2dd4bf', border: '#5eead4' },
       purple: { fill: '#a855f7', stroke: '#c084fc', border: '#c4b5fd' }
     };

     function drawToken(q, r, playerId, color) {
       const { x, y } = axialToPixel(q, r);
       const cx = x + 400;
       const cy = y + 400;
       const tokenSize = s * 0.6; // Token size relative to hex
       const radius = tokenSize * 0.3;
       const glowRadius = tokenSize * 0.36;

       const tokenGroup = document.createElementNS("http://www.w3.org/2000/svg", "g");
       tokenGroup.setAttribute("class", "player-token");
       tokenGroup.setAttribute("data-player-id", playerId);
       tokenGroup.setAttribute("data-q", q);
       tokenGroup.setAttribute("data-r", r);
       tokenGroup.style.cursor = "pointer";

       const colors = colorMap[color] || colorMap.blue;

       // Glow effect
       const glow = document.createElementNS("http://www.w3.org/2000/svg", "circle");
       glow.setAttribute("cx", cx);
       glow.setAttribute("cy", cy);
       glow.setAttribute("r", glowRadius);
       glow.setAttribute("fill", colors.fill);
       glow.setAttribute("opacity", "0.2");
       tokenGroup.appendChild(glow);

       // Token background
       const bg = document.createElementNS("http://www.w3.org/2000/svg", "circle");
       bg.setAttribute("cx", cx);
       bg.setAttribute("cy", cy);
       bg.setAttribute("r", radius);
       bg.setAttribute("fill", colors.fill);
       bg.setAttribute("fill-opacity", "0.5");
       bg.setAttribute("stroke", colors.stroke);
       bg.setAttribute("stroke-width", "2");
       tokenGroup.appendChild(bg);

       // Token border
       const border = document.createElementNS("http://www.w3.org/2000/svg", "circle");
       border.setAttribute("cx", cx);
       border.setAttribute("cy", cy);
       border.setAttribute("r", radius);
       border.setAttribute("fill", "none");
       border.setAttribute("stroke", colors.border);
       border.setAttribute("stroke-width", "3");
       tokenGroup.appendChild(border);

       svg.appendChild(tokenGroup);
     }

     function getAdjacentPositions(q, r) {
       const directions = [
         { q: 1, r: 0 },
         { q: 1, r: -1 },
         { q: 0, r: -1 },
         { q: -1, r: 0 },
         { q: -1, r: 1 },
         { q: 0, r: 1 }
       ];

       return directions
         .map(dir => ({ q: q + dir.q, r: r + dir.r }))
         .filter(pos => isOnBoard(pos.q, pos.r));
     }

     function highlightAdjacent(q, r) {
       const adjacent = getAdjacentPositions(q, r);
       adjacent.forEach(pos => {
         const cell = boardState.cells[`${pos.q},${pos.r}`];
         if (cell && !cell.piece) {
           const { x, y } = axialToPixel(pos.q, pos.r);
           const hex = document.createElementNS("http://www.w3.org/2000/svg", "polygon");
           hex.setAttribute("points", hexPoints(x + 400, y + 400));
           hex.setAttribute("fill", "rgba(59, 130, 246, 0.3)");
           hex.setAttribute("stroke", "#3b82f6");
           hex.setAttribute("stroke-width", "2");
           hex.setAttribute("class", "move-highlight");
           hex.style.cursor = "pointer";
           hex.setAttribute("data-q", pos.q);
           hex.setAttribute("data-r", pos.r);
           svg.appendChild(hex);
         }
       });
     }

     function clearHighlights() {
       const highlights = svg.querySelectorAll(".move-highlight");
       highlights.forEach(el => el.remove());
     }

     let selectedToken = null;

     // Draw all coordinates that belong to the star
     for (let q = -8; q <= 8; q++) {
       for (let r = -8; r <= 8; r++) {
         if (isOnBoard(q, r)) {
           drawHex(q, r, colorFor(q, r));
         }
       }
     }

     // Render tokens from board state
     function renderTokens() {
       // Remove existing tokens
       const existingTokens = svg.querySelectorAll(".player-token");
       existingTokens.forEach(token => token.remove());

       // Draw tokens
       Object.keys(boardState.cells).forEach(key => {
         const cell = boardState.cells[key];
         if (cell && cell.piece) {
           const player = boardState.players[cell.piece];
           if (player && player.color) {
             drawToken(cell.q, cell.r, cell.piece, player.color);
           }
         }
       });
     }

     // Token click handler
     svg.addEventListener("click", (e) => {
       const token = e.target.closest(".player-token");
       const highlight = e.target.closest(".move-highlight");

       if (token) {
         clearHighlights();
         const q = parseInt(token.getAttribute("data-q"));
         const r = parseInt(token.getAttribute("data-r"));
         selectedToken = { q, r, playerId: token.getAttribute("data-player-id") };
         highlightAdjacent(q, r);
       } else if (highlight && selectedToken) {
         const toQ = parseInt(highlight.getAttribute("data-q"));
         const toR = parseInt(highlight.getAttribute("data-r"));
         
         // Move token (would call backend API here)
         console.log(`Move token from (${selectedToken.q}, ${selectedToken.r}) to (${toQ}, ${toR})`);
         
         // Update local state (in real app, this would come from backend)
         const fromKey = `${selectedToken.q},${selectedToken.r}`;
         const toKey = `${toQ},${toR}`;
         if (boardState.cells[fromKey]) {
           boardState.cells[fromKey].piece = null;
         }
         if (boardState.cells[toKey]) {
           boardState.cells[toKey].piece = selectedToken.playerId;
         }
         
         clearHighlights();
         selectedToken = null;
         renderTokens();
       } else {
         clearHighlights();
         selectedToken = null;
       }
     });

     // Initialize with empty board state (will be populated from backend)
     // Example structure:
     // boardState = {
     //   cells: {
     //     "1,-5": { q: 1, r: -5, piece: 1 },
     //     "2,-5": { q: 2, r: -5, piece: 1 },
     //     ...
     //   },
     //   players: {
     //     1: { id: 1, color: "blue", name: "Player 1" },
     //     ...
     //   }
     // };
   </script>
 </body>
</html>
