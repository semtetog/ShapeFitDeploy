UPDATE sf_user_profiles
SET exercise_frequency = '1_2x_week'
WHERE (exercise_type IS NOT NULL AND exercise_type != '' AND exercise_type != '0')
  AND (exercise_frequency IS NULL OR exercise_frequency = '' OR exercise_frequency = 'sedentary');

UPDATE sf_user_profiles
SET exercise_frequency = 'sedentary'
WHERE (exercise_type IS NULL OR exercise_type = '' OR exercise_type = '0')
  AND (exercise_frequency IS NULL OR exercise_frequency = '' OR exercise_frequency NOT IN ('sedentary', '1_2x_week', '3_4x_week', '5_6x_week', '6_7x_week', '7plus_week'));

