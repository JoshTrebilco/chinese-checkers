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
    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] flex p-6 lg:p-8 items-center lg:justify-center min-h-screen flex-col">
        <svg id="board" width="800" height="800"
        viewBox="0 0 800 800"
        xmlns="http://www.w3.org/2000/svg"></svg>

   <script>
     const s = 25; // hex radius
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
       const sum = q + r;
       if (r <= -5) return "#93c5fd";        // North - blue-00
       if (r >= 5) return "#fca5a5";         // South - red-300
       if (q >= 5) return "#fde68a";         // NE - yellow-300
       if (q <= -5) return "#86efac";        // SW - green-300
       if (sum <= -5) return "#c4b5fd";      // NW - purple-300
       if (sum >= 5) return "#5eead4";       // SE - teal-300
       return "#fafafa";                     // center area
     }

     // Draw all coordinates that belong to the star
     for (let q = -8; q <= 8; q++) {
       for (let r = -8; r <= 8; r++) {
         if (isOnBoard(q, r)) {
           drawHex(q, r, colorFor(q, r));
         }
       }
     }
   </script>
 </body>
</html>
