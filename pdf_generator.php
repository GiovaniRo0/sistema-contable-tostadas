<?php
require_once('fpdf/fpdf.php');

class PDFGenerator extends FPDF {
    
    protected $empresaNombre = "Tostadas Jela";
    protected $empresaDireccion = "";
    protected $empresaTelefono = "";
    protected $tituloDocumento = "";
    protected $subtituloDocumento = "";
    
    function Header() {
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, iconv('UTF-8', 'windows-1252', $this->empresaNombre), 0, 1, 'C');
        
        if (!empty($this->empresaDireccion)) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, iconv('UTF-8', 'windows-1252', $this->empresaDireccion), 0, 1, 'C');
        }
        
        if (!empty($this->empresaTelefono)) {
            $this->Cell(0, 6, iconv('UTF-8', 'windows-1252', $this->empresaTelefono), 0, 1, 'C');
        }
        
        $this->Line(10, $this->GetY() + 2, 200, $this->GetY() + 2);
        $this->Ln(8);
        
        if ($this->tituloDocumento) {
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, iconv('UTF-8', 'windows-1252', $this->tituloDocumento), 0, 1, 'C');
        }
        
        if ($this->subtituloDocumento) {
            $this->SetFont('Arial', 'I', 10);
            $this->Cell(0, 6, iconv('UTF-8', 'windows-1252', $this->subtituloDocumento), 0, 1, 'C');
        }
        
        $this->Ln(5);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        $this->Cell(0, 10, 'Generado: ' . date('d/m/Y H:i:s'), 0, 0, 'R');
    }
    
    function setTitulo($titulo) {
        $this->tituloDocumento = $titulo;
    }
    
    function setSubtitulo($subtitulo) {
        $this->subtituloDocumento = $subtitulo;
    }
    
    function texto($texto) {
        return iconv('UTF-8', 'windows-1252', $texto);
    }
    
    function crearTabla($cabeceras, $datos, $anchos = null, $alineaciones = null) {
        if (!$anchos) {
            $anchos = array_fill(0, count($cabeceras), 190 / count($cabeceras));
        }
        
        if (!$alineaciones) {
            $alineaciones = array_fill(0, count($cabeceras), 'L');
        }
        
        $this->SetFillColor(59, 89, 152);
        $this->SetTextColor(255);
        $this->SetDrawColor(50, 50, 50);
        $this->SetLineWidth(0.3);
        $this->SetFont('Arial', 'B', 10);
        
        foreach ($cabeceras as $i => $col) {
            $this->Cell($anchos[$i], 8, $this->texto($col), 1, 0, 'C', true);
        }
        $this->Ln();
        
        $this->SetFillColor(240, 240, 240);
        $this->SetTextColor(0);
        $this->SetFont('Arial', '', 9);
        
        $fill = false;
        foreach ($datos as $fila) {
            if ($this->GetY() > 260) {
                $this->AddPage();
                $this->SetFillColor(59, 89, 152);
                $this->SetTextColor(255);
                $this->SetFont('Arial', 'B', 10);
                foreach ($cabeceras as $i => $col) {
                    $this->Cell($anchos[$i], 8, $this->texto($col), 1, 0, 'C', true);
                }
                $this->Ln();
                $this->SetFillColor(240, 240, 240);
                $this->SetTextColor(0);
                $this->SetFont('Arial', '', 9);
            }
            
            foreach ($fila as $i => $col) {
                $this->Cell($anchos[$i], 6, $this->texto($col), 'LR', 0, $alineaciones[$i], $fill);
            }
            $this->Ln();
            $fill = !$fill;
        }
        
        $this->Cell(array_sum($anchos), 0, '', 'T');
        $this->Ln(5);
    }
    
    function agregarFiltros($filtros) {
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, $this->texto('Filtros aplicados:'), 0, 1);
        
        $this->SetFont('Arial', '', 9);
        foreach ($filtros as $key => $value) {
            if (!empty($value)) {
                $this->Cell(0, 6, $this->texto('• ' . ucfirst($key) . ': ' . $value), 0, 1);
            }
        }
        $this->Ln(5);
    }
    
    function agregarResumen($resumen) {
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 8, $this->texto('Resumen:'), 0, 1);
        
        $this->SetFont('Arial', '', 10);
        foreach ($resumen as $key => $value) {
            $this->Cell(0, 6, $this->texto('• ' . $key . ': ' . $value), 0, 1);
        }
        $this->Ln(5);
    }
}
?>