<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shartnoma</title>
    <style>
        /* Remove yellow highlight background from templates */
        * {
            background-color: transparent !important;
            background: transparent !important;
        }

        body { 
            font-family: 'DejaVu Sans', 'Arial', sans-serif; 
            font-size: 11px; 
            line-height: 1.4; 
            color: #000; 
            margin: 10px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        
        /* Table styles */
        table { 
            width: 100% !important; 
            border-collapse: collapse; 
            margin-top: 15px; 
            margin-bottom: 15px; 
        }
        th, td { 
            border: 1px solid #000; 
            padding: 8px; 
            text-align: left; 
            vertical-align: top;
            font-size: 10px;
        }
        

        /* Style paragraphs */
        p { 
            margin-bottom: 8px; 
            text-align: justify; 
            text-indent: 20px; 
        }
        
        /* Style images inside table cells (like tiger logo or headers) */
        img { 
            max-height: 75px !important; /* Limit height of any logo to 75px so it doesn't take too much space */
            width: auto !important; 
            display: block; 
            margin: 0 auto; 
        }
    </style>
</head>
<body>
    {!! $content !!}
</body>
</html>
