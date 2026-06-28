<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shartnoma</title>
    <style>
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
        
        /* Ensure signature/address tables with exactly 2 columns are split 50-50 */
        tr td:first-child:nth-last-child(2),
        tr td:first-child:nth-last-child(2) ~ td {
            width: 50% !important;
        }
        
        /* Style paragraphs */
        p { 
            margin-bottom: 8px; 
            text-align: justify; 
            text-indent: 20px; 
        }
        
        /* Style images inside table cells (like tiger logo or headers) */
        img { 
            max-width: 100% !important; 
            height: auto !important; 
            display: block; 
            margin: 0 auto; 
        }
    </style>
</head>
<body>
    {!! $content !!}
</body>
</html>
