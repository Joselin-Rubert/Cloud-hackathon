-- ============================================================
-- StudentFlow - Demo Data
-- Import AFTER studentflow.sql
-- Login: demo@studentflow.app / demo1234
-- ============================================================
USE studentflow;

INSERT INTO users (name, email, password, college, department, year)
VALUES ('Demo Student', 'demo@studentflow.app',
        '$2y$10$IR4/uGiHe0EmqbYDze0HnObKfuMW2T4SLIsQDObFyZSQjV0/LwyuW',
        'City Institute of Technology', 'Computer Science', '3rd Year')
ON DUPLICATE KEY UPDATE id = id;

SET @uid = (SELECT id FROM users WHERE email = 'demo@studentflow.app');

-- ------------------------------------------------------------
-- tasks (one overdue, two today, one upcoming, one completed)
-- ------------------------------------------------------------
INSERT INTO tasks (user_id, title, description, category, priority, due_date, due_time, estimated_minutes, status, created_at)
VALUES
(@uid, 'Submit DBMS assignment', 'ER diagram + normalization for the library system', 'Assignment', 'High', CURDATE() - INTERVAL 2 DAY, '23:59', 90, 'Not Started', NOW()),
(@uid, 'Prepare for Operating Systems exam', 'Chapters 4-6: scheduling and deadlocks', 'Exam', 'High', CURDATE(), NULL, 120, 'In Progress', NOW()),
(@uid, 'Finish project proposal draft', 'Two-page outline for the Smart Campus project', 'Project', 'Medium', CURDATE(), '18:00', 60, 'Not Started', NOW()),
(@uid, 'Read Data Structures notes', 'Trees and graphs revision', 'Study', 'Low', CURDATE() + INTERVAL 3 DAY, NULL, 45, 'Not Started', NOW()),
(@uid, 'Renew library book', 'Return "Clean Code" before the due date', 'Personal', 'Medium', CURDATE() - INTERVAL 5 DAY, NULL, 15, 'Completed', NOW() - INTERVAL 6 DAY);

-- ------------------------------------------------------------
-- events / calendar
-- ------------------------------------------------------------
INSERT INTO events (user_id, title, description, event_date, start_time, end_time, category)
VALUES
(@uid, 'OS Unit Test', 'Scheduling and deadlock unit test', CURDATE() + INTERVAL 2 DAY, '10:00', '11:30', 'Exam'),
(@uid, 'Project team meeting', 'Discuss module split and timeline', CURDATE() + INTERVAL 1 DAY, '16:00', '17:00', 'Project'),
(@uid, 'Hackathon kickoff', 'Idea finalisation and team sync', CURDATE() + INTERVAL 5 DAY, '09:00', '12:00', 'Other');

-- ------------------------------------------------------------
-- subjects + study sessions
-- ------------------------------------------------------------
INSERT INTO subjects (user_id, name, difficulty, exam_date)
VALUES
(@uid, 'Operating Systems', 'Hard', CURDATE() + INTERVAL 10 DAY),
(@uid, 'Database Management', 'Medium', CURDATE() + INTERVAL 18 DAY);

INSERT INTO study_sessions (user_id, subject_id, topic, duration_minutes, session_date, notes)
SELECT @uid, s.id, ss.topic, ss.duration_minutes, ss.session_date, ss.notes
FROM (SELECT 1) dummy
JOIN subjects s ON s.user_id = @uid AND s.name = 'Operating Systems'
CROSS JOIN (
  SELECT 'Process scheduling algorithms' AS topic, 60 AS duration_minutes, CURDATE() AS session_date, 'Completed FCFS and SJF examples' AS notes
  UNION ALL
  SELECT 'Deadlock avoidance', 45, CURDATE() - INTERVAL 1 DAY, 'Banker algorithm practice problems'
  UNION ALL
  SELECT 'Indexing in databases', 90, CURDATE() - INTERVAL 3 DAY, 'B+ tree notes revised'
) ss;

-- ------------------------------------------------------------
-- expenses (within the current week/month)
-- ------------------------------------------------------------
INSERT INTO expenses (user_id, amount, category, description, expense_date)
VALUES
(@uid, 120.00, 'Food', 'Canteen lunch', CURDATE() - INTERVAL 1 DAY),
(@uid, 45.00, 'Travel', 'Bus pass recharge', CURDATE() - INTERVAL 2 DAY),
(@uid, 350.00, 'Education', 'Lab record book + prints', CURDATE() - INTERVAL 3 DAY),
(@uid, 199.00, 'Entertainment', 'Movie with friends', CURDATE() - INTERVAL 5 DAY),
(@uid, 75.50, 'Food', 'Evening snacks', CURDATE()),
(@uid, 1200.00, 'Shopping', 'Headphones', CURDATE() - INTERVAL 8 DAY);

-- ------------------------------------------------------------
-- habits + logs (last 14 days)
-- ------------------------------------------------------------
INSERT INTO habits (user_id, name, description, frequency, target)
VALUES
(@uid, 'Morning workout', '30 min exercise', 'daily', 1),
(@uid, 'Read 20 pages', 'Any non-academic book', 'daily', 1),
(@uid, 'Drink 8 glasses of water', 'Stay hydrated', 'daily', 8),
(@uid, 'No social media before noon', 'Deep work in the morning', 'daily', 1);

INSERT IGNORE INTO habit_logs (habit_id, user_id, log_date, completed)
SELECT h.id, @uid, CURDATE() - INTERVAL n.n DAY, 1
FROM habits h
JOIN (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
      UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) n
WHERE h.user_id = @uid AND h.name IN ('Morning workout', 'Drink 8 glasses of water');

INSERT IGNORE INTO habit_logs (habit_id, user_id, log_date, completed)
SELECT h.id, @uid, CURDATE() - INTERVAL n.n DAY, 1
FROM habits h
JOIN (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 3
      UNION ALL SELECT 4 UNION ALL SELECT 7 UNION ALL SELECT 8) n
WHERE h.user_id = @uid AND h.name = 'Read 20 pages';

-- ------------------------------------------------------------
-- goals
-- ------------------------------------------------------------
INSERT INTO goals (user_id, title, description, category, target_date, progress, status)
VALUES
(@uid, 'Reach 8.5 CGPA this semester', 'Consistent daily study blocks and weekly revision', 'Education', CURDATE() + INTERVAL 60 DAY, 60, 'On Track'),
(@uid, 'Build and deploy capstone project', 'Full stack student portal with PHP + MySQL', 'Career', CURDATE() + INTERVAL 40 DAY, 35, 'At Risk'),
(@uid, 'Run 5K without stopping', 'Train 4 times a week', 'Health', CURDATE() - INTERVAL 10 DAY, 100, 'Completed');

-- ------------------------------------------------------------
-- focus sessions
-- ------------------------------------------------------------
INSERT INTO focus_sessions (user_id, task_id, duration_minutes, session_date, completed)
VALUES
(@uid, NULL, 25, CURDATE(), 1),
(@uid, NULL, 45, CURDATE() - INTERVAL 1 DAY, 1),
(@uid, NULL, 50, CURDATE() - INTERVAL 2 DAY, 1);

-- ------------------------------------------------------------
-- notifications
-- ------------------------------------------------------------
INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
VALUES
(@uid, 'Assignment overdue', 'DBMS assignment was due 2 days ago.', 'overdue', 0, NOW() - INTERVAL 1 DAY),
(@uid, 'Exam coming up', 'Operating Systems exam in 10 days.', 'exam', 0, NOW() - INTERVAL 6 HOUR),
(@uid, 'Habit streak', 'Morning workout — 7 day streak. Keep it going!', 'habit', 1, NOW() - INTERVAL 2 DAY),
(@uid, 'Budget update', 'You have spent 62% of this month''s budget.', 'budget', 0, NOW() - INTERVAL 12 HOUR),
(@uid, 'Welcome to StudentFlow', 'Add your first task to get a prioritized recommendation.', 'system', 1, NOW() - INTERVAL 5 DAY);

-- ------------------------------------------------------------
-- settings
-- ------------------------------------------------------------
INSERT INTO user_settings (user_id, dark_mode, daily_study_target, monthly_budget, email_notifications, task_reminders, habit_reminders)
VALUES (@uid, 0, 180, 8000.00, 1, 1, 1)
ON DUPLICATE KEY UPDATE daily_study_target = 180, monthly_budget = 8000.00;
