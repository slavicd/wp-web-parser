<div class="wrap">
    <h2>Sources</h2>

    <div class="source-list">
        <ul>
            <?php foreach ($parsers as $key=>$data): ?>
                <li><a href="<?php echo $data['link_to']; ?>" <?php if (isset($_GET['sp']) && $key==$_GET['sp']) echo 'class="active"'; ?>><?php echo $data['name'] ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if ($selectedParser): ?>
        <form action="" method="post" class="settings">
            <input type="hidden" name="operation" value="save" />
            <ul>
                <li>
                    <label>Name</label>
                    <p><?php echo $selectedParser['name']; ?></p>
                </li>
                <li>
                    <label>Schedule</label>
                    <select name="schedule">
                        <option value="disabled">Not running</option>
                        <?php foreach ($schedules as $key=>$value): ?>
                            <option value="<?php echo $key; ?>"
                                <?php if (isset($selectedParser['schedule']) && $selectedParser['schedule']==$key) echo 'selected' ?>><?php echo $value; ?></option>
                        <?php endforeach; ?>
                    </select>
                </li>
                <li>
                    <label>Limit number of parsed posts</label>
                    <input type="number" min="0" step="1" name="max_posts" value="<?php echo $selectedParser['max_posts'] ?>" title="Set 0 for no limits"/>
                </li>
                <li>
                    <label>Update existing</label>
                    <select name="should_update">
                        <option value="no" <?php if(isset($selectedParser['should_update']) && $selectedParser['should_update']=='no') echo 'selected'; ?>>No</option>
                        <option value="yes" <?php if(isset($selectedParser['should_update']) && $selectedParser['should_update']=='yes') echo 'selected'; ?>>Yes</option>
                        <option value="old" <?php if(isset($selectedParser['should_update']) && $selectedParser['should_update']=='old') echo 'selected'; ?>>Older than 30 days</option>
                    </select>
                </li>
                <li>
                    <label>&nbsp;</label>
                    <button type="submit">Save</button>
                </li>
            </ul>
        </form>

        <div class="log-section">
            <form action="" method="post" id="entropi-run-parser">
                <input type="hidden" name="operation" />
                <button type="submit"><?php if (isset($selectedParser['started']) && $selectedParser['started']): ?>Stop<?php else: ?>Start<?php endif; ?></button>
            </form>
            <ul>
                <?php foreach($logEntries as $entry): ?>
                    <li><code><?php echo $entry->time; ?>: <?php echo $entry->text; ?></code></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <div>
            <p>Select a parser to edit its settings. To enable more parsers, add the corresponding plugins.</p>
        </div>
    <?php endif; ?>
</div>