{{--
    Dicitura UNICA di tutela del diritto d'autore per le pagine a schermo,
    gemella del footer stampato sui PDF (CopyrightTcpdf) e sulle slide
    (build_pptx.py). Testo da copyright_notice(): mai scriverlo a mano qui.

    `print-color-adjust` + la regola @media print servono perché il canvas dei
    corsi viene stampato dal browser: la dicitura deve finire sulla carta, non
    sparire come tanti footer "solo schermo".
--}}
@php($noscCopyright = copyright_notice())
@if($noscCopyright !== '')
<footer class="nosc-copyright" role="contentinfo">
    <span>{{ $noscCopyright }}</span>
</footer>
<style>
    .nosc-copyright {
        padding: 16px 24px 24px;
        text-align: center;
        font-size: 0.6875rem;
        line-height: 1.4;
        color: #8A9696;
        border-top: 1px solid rgba(85, 177, 174, 0.12);
    }
    @media print {
        .nosc-copyright {
            display: block !important;
            color: #666 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
@endif
