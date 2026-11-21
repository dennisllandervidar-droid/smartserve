<?php
if (defined('STUDENT_FAQS_WIDGET_RENDERED')) {
    return;
}
define('STUDENT_FAQS_WIDGET_RENDERED', true);

$student_faq_items = [];
if (isset($faqs) && is_array($faqs)) {
    $student_faq_items = $faqs;
} elseif (isset($model) && method_exists($model, 'displayFaqs')) {
    $student_faq_items = $model->displayFaqs(1);
}
?>
<style>
.student-faq-toggle {
    position: fixed;
    right: 22px;
    bottom: 20px;
    z-index: 1300;
    border: 0;
    background: #0f7b5f;
    color: #ffffff !important;
    border-radius: 999px;
    padding: 11px 16px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    box-shadow: 0 10px 24px rgba(10, 35, 29, 0.25);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    -webkit-appearance: none;
    appearance: none;
}

.student-faq-toggle:hover,
.student-faq-toggle:focus,
.student-faq-toggle:active {
    color: #ffffff !important;
    text-decoration: none;
    transform: translateY(-1px);
    box-shadow: 0 12px 28px rgba(10, 35, 29, 0.3);
}

.student-faq-overlay {
    position: fixed;
    inset: 0;
    background: rgba(8, 30, 24, 0.45);
    backdrop-filter: blur(1px);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s ease;
    z-index: 1298;
}

.student-faq-panel {
    position: fixed;
    right: 20px;
    bottom: 74px;
    width: min(390px, calc(100vw - 24px));
    max-height: 76vh;
    background: #ffffff;
    border: 1px solid #dfe9e5;
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(15, 43, 35, 0.28);
    overflow: hidden;
    opacity: 0;
    transform: translateY(14px) scale(0.98);
    visibility: hidden;
    transition: opacity 0.2s ease, transform 0.2s ease;
    z-index: 1299;
}

.student-faq-panel.open,
.student-faq-overlay.open {
    opacity: 1;
    visibility: visible;
}

.student-faq-panel.open {
    transform: translateY(0) scale(1);
}

.student-faq-head {
    background: linear-gradient(135deg, #0b614b, #0a4e3d);
    color: #ffffff;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.student-faq-head h5 {
    margin: 0;
    font-size: 0.98rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #ffffff !important;
}

.student-faq-head h5 i,
.student-faq-toggle i {
    color: #ffffff !important;
}

.student-faq-close {
    border: 0;
    background: transparent;
    color: #ffffff;
    font-size: 1.1rem;
    line-height: 1;
    opacity: 0.9;
}

.student-faq-body {
    padding: 12px;
    overflow-y: auto;
    max-height: calc(76vh - 62px);
    background: #f8fbfa;
}

.student-faq-item {
    border: 1px solid #dbe9e4;
    border-radius: 10px;
    background: #ffffff;
    margin-bottom: 10px;
    overflow: hidden;
}

.student-faq-question {
    width: 100%;
    border: 0;
    background: #ffffff;
    color: #134336;
    text-align: left;
    font-weight: 700;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    cursor: pointer;
    box-shadow: none !important;
    outline: none;
    -webkit-appearance: none;
    appearance: none;
}

.student-faq-question:focus,
.student-faq-question:active {
    outline: none;
    box-shadow: none !important;
}

.student-faq-question .caret {
    color: #0f7b5f;
    transition: transform 0.18s ease;
}

.student-faq-item.open .student-faq-question {
    background: #edf6f2;
    border-bottom: 1px solid #d7e7e1;
}

.student-faq-item.open .caret {
    transform: rotate(180deg);
}

.student-faq-answer {
    display: none;
    color: #355b52;
    font-size: 0.9rem;
    line-height: 1.55;
    padding: 10px 14px 12px;
    background: #ffffff;
}

.student-faq-item.open .student-faq-answer {
    display: block;
}

.student-faq-close:focus,
.student-faq-close:active,
.student-faq-toggle:focus,
.student-faq-toggle:active {
    outline: none;
    box-shadow: none !important;
}

@media (max-width: 768px) {
    .student-faq-toggle {
        right: 12px;
        bottom: 12px;
        padding: 10px 14px;
        font-size: 0.85rem;
    }

    .student-faq-panel {
        right: 12px;
        bottom: 64px;
        width: calc(100vw - 24px);
        max-height: 74vh;
    }
}
</style>

<button type="button" class="student-faq-toggle" id="studentFaqToggleBtn">
    <i class="ti-help"></i>
    FAQs
</button>

<div class="student-faq-overlay" id="studentFaqOverlay"></div>

<section class="student-faq-panel" id="studentFaqPanel" aria-label="Frequently Asked Questions">
    <div class="student-faq-head">
        <h5><i class="ti-comments"></i> Frequently Asked Questions</h5>
        <button type="button" class="student-faq-close" id="studentFaqCloseBtn" aria-label="Close FAQs">&times;</button>
    </div>
    <div class="student-faq-body">
        <?php if (!empty($student_faq_items)): ?>
            <?php foreach ($student_faq_items as $faq_index => $faq_item): ?>
                <div class="student-faq-item <?php echo $faq_index === 0 ? 'open' : ''; ?>">
                    <button type="button" class="student-faq-question">
                        <span><?php echo htmlspecialchars($faq_item['question']); ?></span>
                        <span class="caret"><i class="ti-angle-down"></i></span>
                    </button>
                    <div class="student-faq-answer">
                        <?php echo nl2br(htmlspecialchars($faq_item['answer'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="student-faq-item open">
                <div class="student-faq-answer" style="display:block; padding-top: 12px;">
                    No FAQs are available at the moment.
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    var toggleBtn = document.getElementById('studentFaqToggleBtn');
    var closeBtn = document.getElementById('studentFaqCloseBtn');
    var panel = document.getElementById('studentFaqPanel');
    var overlay = document.getElementById('studentFaqOverlay');

    if (!toggleBtn || !panel || !overlay) {
        return;
    }

    function openPanel() {
        panel.classList.add('open');
        overlay.classList.add('open');
        document.body.classList.add('student-faq-open');
    }

    function closePanel() {
        panel.classList.remove('open');
        overlay.classList.remove('open');
        document.body.classList.remove('student-faq-open');
    }

    toggleBtn.addEventListener('click', function () {
        if (panel.classList.contains('open')) {
            closePanel();
        } else {
            openPanel();
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closePanel);
    }

    overlay.addEventListener('click', closePanel);

    panel.addEventListener('click', function (event) {
        var questionBtn = event.target.closest('.student-faq-question');
        if (!questionBtn) {
            return;
        }

        var item = questionBtn.closest('.student-faq-item');
        if (!item) {
            return;
        }

        var items = panel.querySelectorAll('.student-faq-item');
        items.forEach(function (entry) {
            if (entry !== item) {
                entry.classList.remove('open');
            }
        });
        item.classList.toggle('open');
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closePanel();
        }
    });
})();
</script>
