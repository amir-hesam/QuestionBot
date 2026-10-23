<?php
if (!defined('TELEBOT_PANEL')) {
    exit;
}

$db = App::db();
$quizId = Panel::intGet('quiz', 0);
$quiz = $quizId > 0 ? $db->one('SELECT * FROM quizzes WHERE id = ?', array($quizId)) : null;

if (!$quiz) {
    echo '<div class="alert err">آزمون پیدا نشد.</div>';
    echo '<p><a class="btn ghost" href="' . Panel::h(Panel::url('quizzes')) . '">بازگشت به آزمون‌ها</a></p>';
    return;
}

$questionId = Panel::intGet('id', 0);
$question = null;
if ($questionId > 0) {
    $question = $db->one(
        'SELECT * FROM questions WHERE id = ? AND quiz_id = ?',
        array($questionId, $quizId)
    );
    if (!$question) {
        echo '<div class="alert err">سوال پیدا نشد.</div>';
        echo '<p><a class="btn ghost" href="' . Panel::h(Panel::url('quiz', array('id' => $quizId))) . '">بازگشت به آزمون</a></p>';
        return;
    }
}

$pageTitle = $question ? 'ویرایش سوال' : 'افزودن سوال';
$errors = array();
$form = $question ? $question : array(
    'text' => '',
    'option_a' => '',
    'option_b' => '',
    'option_c' => '',
    'option_d' => '',
    'correct' => 'A',
    'position' => '',
);

if (Panel::isPost()) {
    $text = Panel::rawPost('text');
    $optionA = Panel::post('option_a');
    $optionB = Panel::post('option_b');
    $optionC = Panel::post('option_c');
    $optionD = Panel::post('option_d');
    $correct = strtoupper(Panel::post('correct'));
    $positionRaw = Panel::post('position');
    $position = $positionRaw === '' ? 0 : (int) $positionRaw;

    if (Farsi::len($text) < 1 || Farsi::len($text) > 3000) {
        $errors[] = 'متن سوال باید بین ۱ تا ۳۰۰۰ کاراکتر باشد.';
    }
    foreach (array('الف' => $optionA, 'ب' => $optionB, 'پ' => $optionC, 'ت' => $optionD) as $label => $option) {
        if (Farsi::len($option) < 1 || Farsi::len($option) > 500) {
            $errors[] = 'گزینه «' . $label . '» باید بین ۱ تا ۵۰۰ کاراکتر باشد.';
        }
    }
    if (!in_array($correct, array('A', 'B', 'C', 'D'), true)) {
        $errors[] = 'پاسخ درست را انتخاب کنید.';
    }
    if ($positionRaw !== '' && ($position < 1 || $position > 10000)) {
        $errors[] = 'شماره ترتیب باید بین ۱ تا ۱۰۰۰۰ باشد.';
    }

    $form = array(
        'text' => $text,
        'option_a' => $optionA,
        'option_b' => $optionB,
        'option_c' => $optionC,
        'option_d' => $optionD,
        'correct' => $correct,
        'position' => $positionRaw,
    );

    if (count($errors) === 0) {
        if ($question) {
            $db->exec(
                'UPDATE questions
                    SET text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct = ?,
                        position = ?
                  WHERE id = ? AND quiz_id = ?',
                array(
                    $text,
                    $optionA,
                    $optionB,
                    $optionC,
                    $optionD,
                    $correct,
                    $position > 0 ? $position : (int) $question['position'],
                    $questionId,
                    $quizId,
                )
            );
            Panel::flashRedirect('ok', 'سوال ذخیره شد.', Panel::url('quiz', array('id' => $quizId)));
        }
        if ($position <= 0) {
            $position = (int) $db->val(
                'SELECT COALESCE(MAX(position), 0) + 1 FROM questions WHERE quiz_id = ?',
                array($quizId),
                1
            );
        }
        $db->exec(
            'INSERT INTO questions (quiz_id, position, text, option_a, option_b, option_c, option_d, correct, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array($quizId, $position, $text, $optionA, $optionB, $optionC, $optionD, $correct, App::now())
        );
        Panel::flashRedirect('ok', 'سوال اضافه شد.', Panel::url('quiz', array('id' => $quizId)));
    }
}

$formPosition = $form['position'];
if ($formPosition === '' && $question) {
    $formPosition = (int) $question['position'];
}
?>
<?php if (count($errors) > 0): ?>
  <div class="alert err"><?php echo Panel::h(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2><?php echo $question ? 'ویرایش سوال' : 'افزودن سوال جدید'; ?> — <?php echo Panel::h($quiz['title']); ?></h2>
    <a class="btn ghost small" href="<?php echo Panel::h(Panel::url('quiz', array('id' => $quizId))); ?>">بازگشت به آزمون</a>
  </div>

  <form method="post" action="<?php echo Panel::h(Panel::url('question', array('quiz' => $quizId, 'id' => $questionId))); ?>" class="stack">
    <?php echo PanelAuth::csrfField(); ?>
    <label>
      متن سوال
      <textarea name="text" maxlength="3000" required><?php echo Panel::h($form['text']); ?></textarea>
      <span class="field-hint">حداکثر ۳۰۰۰ کاراکتر.</span>
    </label>

    <div class="form-grid">
      <label>
        گزینه الف
        <input type="text" name="option_a" value="<?php echo Panel::h($form['option_a']); ?>" maxlength="500" required>
      </label>
      <label>
        گزینه ب
        <input type="text" name="option_b" value="<?php echo Panel::h($form['option_b']); ?>" maxlength="500" required>
      </label>
      <label>
        گزینه پ
        <input type="text" name="option_c" value="<?php echo Panel::h($form['option_c']); ?>" maxlength="500" required>
      </label>
      <label>
        گزینه ت
        <input type="text" name="option_d" value="<?php echo Panel::h($form['option_d']); ?>" maxlength="500" required>
      </label>
    </div>

    <div class="card">
      <h3>پاسخ درست</h3>
      <div class="row">
        <?php foreach (array('A', 'B', 'C', 'D') as $letter): ?>
          <label class="check">
            <input type="radio" name="correct" value="<?php echo $letter; ?>"<?php echo $form['correct'] === $letter ? ' checked' : ''; ?> required>
            <?php echo Panel::h(Panel::optionLabel($letter)); ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <label>
      ترتیب نمایش (اختیاری)
      <input type="number" name="position" min="1" max="10000" value="<?php echo Panel::h($formPosition); ?>" placeholder="خالی = انتهای لیست">
    </label>

    <div class="row">
      <button class="btn primary" type="submit"><?php echo $question ? 'ذخیره سوال' : 'افزودن سوال'; ?></button>
      <a class="btn ghost" href="<?php echo Panel::h(Panel::url('quiz', array('id' => $quizId))); ?>">انصراف</a>
    </div>
  </form>
</div>
