<?php
/*ini_set('display_errors', 'On');
error_reporting(E_ALL);*/
$request = 'default';
if (isset($_POST['request_type'])) {
    $request = $_POST['request_type'];
} else {
    echo 'no POST found, was a request_type specified?';
    exit();
}

$json = 'default';
$json_obj = 'default';

if (isset($_POST['data'])) {
    $json = $_POST['data'];
    $json_obj = json_decode($json, true);
}

$servername = "sql.njit.edu";
$username = "mg427";
$password = base64_decode("NWNpWU1VNlhm");
$dbname = "mg427";
$conn = new mysqli($servername, $username, $password, $dbname);
switch ($request) {
    case 'login':
        login($json_obj, $conn);
        break;
    case 'new_question':
        new_question($json_obj, $conn);
        break;
    case 'new_exam':
        new_exam($json_obj, $conn);
        break;
    case 'submit':
        submit_exam($json_obj, $conn);
        break;
    case 'query':
        query_question($json_obj, $conn);
        break;
    case 'get_scores':
        get_score($json_obj, $conn);
        break;
    case 'release':
        release($json_obj, $conn);
        break;
    case 'review_grade':
        review_grade($json_obj, $conn);
        break;
    case 'take_exam':
        take_exam($json_obj, $conn);
        break;
    case 'num_questions':
        num_questions($json_obj, $conn);
        break;
    case 'is_released':
        is_released($json_obj, $conn);
        break;
    case 'modify_grade':
        modify_grade($json_obj, $conn);
        break;
    case 'default':
        echo 'This should never happen';
        break;
    default:
        echo 'Invalid request: ' . $request;
        break;
}
$conn->close();

//REQUEST METHODS
function login($json_obj, $conn)
{
    $username = $json_obj['username'];
    $password = $json_obj['password'];

    $response = 'failure';
    //SQL query to check if student login exists
    $sql = "SELECT * FROM STUDENT_LOGIN WHERE username = \"" . $username . "\" AND password = \"" . $password . "\";";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $response = "student";
    }

    //SQL query to check if instructor login exists
    $sql = "SELECT * FROM INSTRUCTOR_LOGIN WHERE username = \"" . $username . "\" AND password = \"" . $password . "\";";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $response = "instructor";
    }

    echo trim($response);

}

function to_list($str)
{
    $comma_index = strpos($str, ',');
    $arg1 = substr($str, 0, $comma_index);
    $arg2 = substr($str, $comma_index + 2);
    return array($arg1, $arg2);
}

function to_str($arr)
{
    return $arr[0] . ', ' . $arr[1];
}

function new_question($json_obj, $conn)
{
    $json_obj = $json_obj["questions"];
    $key = array_keys($json_obj)[0];
    $json_obj = $json_obj[$key];

    $name = $json_obj['func_name'];
    $arg_names = to_str($json_obj['arg_names']);
    $desc = $json_obj['description'];
    $inputs = $json_obj['inputs'];
    $outputs = $json_obj['expected_outputs'];
    $difficulty = $json_obj['difficulty'];
    $topics = $json_obj['topics'];

    $result = "success";


    //Creating the question
    $sql = "INSERT INTO QUESTIONS (Name, Description, Difficulty, Arguments)
	VALUES ('" . $name . "', '" . $desc . "', '" . $difficulty . "', '" . $arg_names . "');";

    if ($conn->query($sql) === FALSE) {
        $result = "fail";
    }

    //Creating the inputs and outputs
    for ($i = 0; $i < count($inputs); $i++) {
        $sql = "INSERT INTO PUTS (QID, Output, Input)
		VALUES ('" . $name . "', '" . $outputs[$i] . "', '" . to_str($inputs[$i]) . "');";
        if ($conn->query($sql) === FALSE) {
            $result = "fail";
        }
    }

    //Creating the topics
    foreach ($topics as $topic) {
        $sql = "INSERT INTO TOPICS (QID, Topic)
		VALUES ('" . $name . "', '" . $topic . "');";
        if ($conn->query($sql) === FALSE) {
            $result = "fail";
        }
    }

    echo $result;
}

function new_exam($json_obj, $conn)
{
    $sql = "DELETE FROM EXAM_QUESTIONS;";
    $conn->query($sql);
    $sql = "DELETE FROM EXAM";
    $conn->query($sql);

    $result = "success";
    $sql = "INSERT INTO EXAM (EID, isReleased)
			VALUES (\"Exam\", 0);";

    if ($conn->query($sql) === false) {
        $result = "fail";
    }

    $points = $json_obj['points'];
    $i = 0;
    foreach ($json_obj['questions'] as $question) {
        $sql = "INSERT INTO EXAM_QUESTIONS (QID, EID, Points)
				VALUES (\"" . $question['func_name'] . "\", \"Exam\", " . $points[$i] . ");";
        if($conn->query($sql) === false){
            $result = "fail";
        }
        $i++;
    }

    echo $result;
}

function submit_exam($json_obj, $conn)
{
    $key = array_keys($json_obj)[0];
    $questions = $json_obj[$key]['questions'];
    $points = $json_obj[$key]['points'];
    $answers = $json_obj[$key]['answers'];
    $comments = $json_obj[$key]['comments'];
    $score = $json_obj[$key]["score"];

    $result = "success";

    for ($i = 0; $i < count($questions); $i++) {
        $sql = "INSERT INTO STUDENT_SCORES (Username, QID, Answer, Comments, Score)
                VALUES ('" . $key . "', '" . $questions[$i + 1]["func_name"] . "', '" . $answers[$i] . "', '" . $comments . "', " . $score . "); ";
        if ($conn->query($sql) === false) {
            echo $conn->error;
            $result = "fail";
        }
    }
    echo $result;
}

function query_question($json_obj, $conn)
{
    $difficulty = $json_obj['difficulty'];
    $topics = $json_obj['topics'];
    $keywords = $json_obj['keywords'];


    $sql = "SELECT * FROM QUESTIONS WHERE
			Difficulty = \"" . $difficulty . "\" AND ";


    for ($i = 0; $i < count($keywords); $i++) {
        $sql .= "Description LIKE \"%" . $keywords[$i] . "%\" AND ";
    }

    $sql .= "Name in (
				SELECT QID
				FROM TOPICS
				WHERE ";

    for ($i = 0; $i < count($topics) - 1; $i++) {
        $sql .= "Topic = \"" . $topics[$i] . "\" OR";
    }

    $sql .= " Topic = \"" . $topics[count($topics) - 1] . "\");";

    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $questions = array();
        while ($row = $result->fetch_assoc()) {
            $QID = $row['Name'];
            $myObj = array();
            $myObj["func_name"] = $QID;
            $myObj["description"] = $row['Description'];
            $myObj["difficulty"] = $row['Difficulty'];
            $myObj["arg_names"] = to_list($row['Arguments']);

            //Time for the hard stuff
            //inputs+outputs
            $puts = $conn->query(
                "SELECT DISTINCT QID, Input, Output 
    		FROM PUTS
    		WHERE QID = \"" . $QID . "\"");
            $inputs = array();
            $outputs = array();
            while ($put = $puts->fetch_assoc()) {
                array_push($inputs, array(to_list($put['Input'])[0], to_list($put['Input'])[1]));
                array_push($outputs, $put['Output']);
            }

            $myObj["inputs"] = $inputs;
            $myObj["expected_outputs"] = $outputs;

            //topics
            $topicsQuery = $conn->query(
                "SELECT DISTINCT QID, Topic 
    		FROM TOPICS
    		WHERE QID = \"" . $QID . "\"");
            $topics = array();
            while ($topic = $topicsQuery->fetch_assoc()) {
                array_push($topics, $topic['Topic']);
            }

            $myObj["topics"] = $topics;

            $questions[$row["Name"]] = $myObj;
        }
        $finalObj = array();
        $finalObj["questions"] = $questions;
        $myJSON = json_encode($finalObj);
        echo $myJSON;
    } else {
        echo "no results";
    }
}

function get_score($json_obj, $conn)
{
    $sql = "SELECT * FROM STUDENT_SCORES
            ORDER BY Username";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $finalObj = array();

        $key = "";
        $questions = array();
        $points = [];
        $answers = "";
        $score = 0;
        $comments = "";

        $first = true;

        while ($row = $result->fetch_assoc()) {
            if ($first) {
                $key = $row["Username"];
                $first = false;
            }

            if ($row["Username"] !== $key) {
                $student = array("questions" => $questions, "points" => $points, "answers" => $answers, "score" => $score, "comments" => $comments);
                $finalObj[$key] = $student;
                $key = $row["Username"];

                $points = [];
            }

            $answers .= $row["Answer"];
            $score = $row["Score"];
            $comments = $row["Comments"];
            //echo "On user " . $row["Username"] . " with question " . $row["QID"] . "<br>";
            //Hunt down the question
            $sql = "SELECT * FROM QUESTIONS WHERE Name = '" . $row["QID"] . "';";
            $questionsQuery = $conn->query($sql);
            if ($questionsQuery->num_rows > 0) {
                $question = array();
                $questionRow = $questionsQuery->fetch_assoc();
                //echo "Queried for question " . $questionRow["Name"] . "<br>";

                $question["func_name"] = $questionRow["Name"];
                $question["arg_names"] = to_list($questionRow["Arguments"]);
                $question["description"] = $questionRow["Description"];
                $question["difficulty"] = $questionRow["Difficulty"];

                $puts = $conn->query(
                    "SELECT DISTINCT QID, INPUT, OUTPUT
    		            FROM PUTS
    		            WHERE QID = \"" . $row["QID"] . "\"");
                $inputs = array();
                $outputs = array();
                while ($put = $puts->fetch_assoc()) {
                    array_push($inputs, array(to_list($put['INPUT'])[0], to_list($put['INPUT'])[1]));
                    array_push($outputs, $put['OUTPUT']);
                }

                $question["inputs"] = $inputs;
                $question["expected_outputs"] = $outputs;

                //topics
                $topicsQuery = $conn->query(
                    "SELECT DISTINCT QID, Topic  
    		              FROM TOPICS
    		              WHERE QID = \"" . $row["QID"] . "\"");
                $topics = array();
                while ($topic = $topicsQuery->fetch_assoc()) {
                    array_push($topics, $topic['Topic']);
                }

                $pointsQuery = $conn->query("
                SELECT *
                FROM EXAM_QUESTIONS 
                WHERE QID = '" . $row["QID"] . "';"
                );
                while ($point = $pointsQuery->fetch_assoc()) {
                    array_push($points, (int)$point["Points"]);
                }

                $question["topics"] = $topics;
                $questions[$questionRow["Name"]] = $question;
            }

        }

        $student = array("questions" => $questions, "points" => $points, "answers" => $answers, "score" => $score, "comments" => $comments);
        $finalObj[$key] = $student;

        $myJSON = json_encode($finalObj);
        echo $myJSON;
    } else {
        echo "No exams";
    }
}

function release($json_obj, $conn)
{
    $flip = 0;
    $sql = "SELECT * FROM EXAM";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if ((int)$row['isReleased'] === 1) {
                $flip = 0;
                break;
            } else {
                $flip = 1;
                break;
            }
        }
    }

    $sql = "UPDATE EXAM
			SET isReleased = " . $flip;
    if ($conn->query($sql) === false) {
        echo trim('fail');
    } else {
        echo trim('success');
    }
}

function review_grade($json_obj, $conn)
{
    //Check if grades have been released
    $isReleasedQuery = "SELECT * FROM EXAM";
    $isReleasedResult = $conn->query($isReleasedQuery);
    $isReleasedRows = $isReleasedResult->fetch_assoc();
    if($isReleasedRows["isReleased"] === 0){
        echo "not released";
        return;
    }


    $username = trim($json_obj["username"]);
    $sql = "SELECT * FROM STUDENT_SCORES
            WHERE Username = '" . $username . "';";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $finalObj = array();

        $key = "";
        $questions = array();
        $points = [];
        $answers = "";
        $score = 0;
        $comments = "";

        while ($row = $result->fetch_assoc()) {
            $answers .= $row["Answer"];
            $score = $row["Score"];
            $comments = $row["Comments"];

            //Hunt down the question
            $sql = "SELECT * FROM QUESTIONS WHERE Name = '" . $row["QID"] . "';";
            $questionsQuery = $conn->query($sql);
            if ($questionsQuery->num_rows > 0) {
                $question = array();
                $questionRow = $questionsQuery->fetch_assoc();

                $question["func_name"] = $questionRow["Name"];
                $question["arg_names"] = to_list($questionRow["Arguments"]);
                $question["description"] = $questionRow["Description"];
                $question["difficulty"] = $questionRow["Difficulty"];

                $puts = $conn->query(
                    "SELECT DISTINCT QID, INPUT, OUTPUT
    		            FROM PUTS
    		            WHERE QID = \"" . $row["QID"] . "\"");
                $inputs = array();
                $outputs = array();
                while ($put = $puts->fetch_assoc()) {
                    array_push($inputs, array(to_list($put['INPUT'])[0], to_list($put['INPUT'])[1]));
                    array_push($outputs, $put['OUTPUT']);
                }

                $question["inputs"] = $inputs;
                $question["expected_outputs"] = $outputs;

                //topics
                $topicsQuery = $conn->query(
                    "SELECT DISTINCT QID, Topic  
    		              FROM TOPICS
    		              WHERE QID = \"" . $row["QID"] . "\"");
                $topics = array();
                while ($topic = $topicsQuery->fetch_assoc()) {
                    array_push($topics, $topic['Topic']);
                }

                $pointsQuery = $conn->query("
                SELECT *
                FROM EXAM_QUESTIONS 
                WHERE QID = '" . $row["QID"] . "';"
                );
                while ($point = $pointsQuery->fetch_assoc()) {
                    array_push($points, (int)$point["Points"]);
                }

                $question["topics"] = $topics;
                $questions[$questionRow["Name"]] = $question;
            }
        }
        $student = array("questions" => $questions, "points" => $points, "answers" => $answers, "score" => $score, "comments" => $comments);
        $finalObj[$username] = $student;
        $myJSON = json_encode($finalObj);
        echo $myJSON;
    }else{
        echo $username . " has not taken an exam";
    }
}

function take_exam($json_obj, $conn)
{
    $sql = "SELECT * FROM EXAM_QUESTIONS";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $finalObj = array();
        $questions = array();
        $points = array();
        while ($row = $result->fetch_assoc()) {
            array_push($points, (int)$row['Points']);
            //Hunt down the question
            $sql = "SELECT * FROM QUESTIONS WHERE Name = '" . $row["QID"] . "';";
            $questionsQuery = $conn->query($sql);
            if ($questionsQuery->num_rows > 0) {
                $question = array();
                $questionRow = $questionsQuery->fetch_assoc();
                $question["func_name"] = $questionRow["Name"];
                $question["arg_names"] = to_list($questionRow["Arguments"]);
                $question["description"] = $questionRow["Description"];
                $question["difficulty"] = $questionRow["Difficulty"];

                $puts = $conn->query(
                    "SELECT DISTINCT QID, Input, Output 
    		            FROM PUTS
    		            WHERE QID = \"" . $row["QID"] . "\"");
                $inputs = array();
                $outputs = array();
                while ($put = $puts->fetch_assoc()) {
                    array_push($inputs, array(to_list($put['Input'])[0], to_list($put['Input'])[1]));
                    array_push($outputs, $put['Output']);
                }

                $question["inputs"] = $inputs;
                $question["expected_outputs"] = $outputs;

                //topics
                $topicsQuery = $conn->query(
                    "SELECT DISTINCT QID, Topic 
    		              FROM TOPICS
    		              WHERE QID = \"" . $row["QID"] . "\"");
                $topics = array();
                while ($topic = $topicsQuery->fetch_assoc()) {
                    array_push($topics, $topic['Topic']);
                }

                $question["topics"] = $topics;
                $questions[$questionRow["Name"]] = $question;
            }
        }

        $finalObj["questions"] = $questions;
        $finalObj["points"] = $points;
        $myJSON = json_encode($finalObj);
        echo $myJSON;
    }
}

function num_questions($json_obj, $conn)
{
    $sql = "SELECT DISTINCT * FROM QUESTIONS";
    $result = $conn->query($sql);
    echo($result->num_rows);
}

function is_released($json_obj, $conn)
{
    $sql = "SELECT * FROM EXAM";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if ((int)$row['isReleased'] === 1) {
                echo('true');
                break;
            } else {
                echo('false');
                break;
            }
        }
    }
}

function modify_grade($json_obj, $conn)
{
    $key = array_keys($json_obj)[0];
    $score = $json_obj[$key]["score"];
    $comments = $json_obj[$key]["comments"];

    $sql = "UPDATE STUDENT_SCORES
            SET Score = " . $score . ", Comments = '" . $comments . "'" .
            " WHERE Username = '" . $key . "';";
    if ($conn->query($sql) === TRUE) {
        echo "success";
    } else {
        echo "fail";
        //echo $conn->error;
    }
}

?>