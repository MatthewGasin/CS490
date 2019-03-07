<?php
ini_set('display_errors', 'On');
error_reporting(E_ALL);
$request = 'default';
if (isset($_POST['request_type'])) {
    $request = $_POST['request_type'];
} else {
    echo '<br>no POST found, was a request_type specified?<br>';
    exit();
}

$json = 'default';
$json_obj = 'default';
$examJSON = file_get_contents('exam.json');

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
    case 'submit_exam':
        submit_exam($json_obj, $conn);
        break;
    case 'query':
        query_question($json_obj, $conn);
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
        echo '<br>This should never happen<br>';
        break;
    default:
        echo '<br>Invalid request: ' . $request . '<br>';
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

    echo $response;

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
		VALUES ('" . $name . "', '" . $outputs[$i] . "', '" . $inputs[$i] . "');";
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
    $sql = "INSERT INTO EXAM (EID, isReleased)
			VALUES (\"Exam\", 0);";
    $conn->query($sql);
    foreach ($json_obj['questions'] as $question) {
        $sql = "INSERT INTO EXAM_QUESTIONS (QID, EID)
				VALUES (\"" . $question['func_name'] . "\", \"Exam\");";
        $conn->query($sql);
    }

}

function submit_exam($json_obj, $conn)
{
    $key = array_keys($json_obj)[0];
    $questions = array_keys($json_obj[$key]['questions']);
    $points = $json_obj[$key]['points'];
    $answers = $json_obj[$key]['answers'];
    $comments = $json_obj[$key]['comments'];

    $result = "success";

    for($i = 0; $i < count($questions); $i++){
       $sql = "INSERT INTO STUDENT_SCORES (Username, QID, Answer, Comments, Score)
                VALUES ('" . $key . "', '" . $questions[$i] . "', '" . $answers[$i] . "', '" . $comments  . "', " . $points[$i]  . ");";
       if($conn->query($sql) === false){
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
            $myObj["arg_names"] = to_list($myObj['Arguments']);

            //Time for the hard stuff
            //inputs+outputs
            $puts = $conn->query(
                "SELECT * 
    		FROM PUTS
    		WHERE QID = \"" . $QID . "\"");
            $inputs = array();
            $outputs = array();
            while ($put = $puts->fetch_assoc()) {
                array_push($inputs, $put['Input']);
                array_push($outputs, $put['Output']);
            }

            //topics
            $topicsQuery = $conn->query(
                "SELECT * 
    		FROM TOPICS
    		WHERE QID = \"" . $QID . "\"");
            $topics = array();
            while ($topic = $topicsQuery->fetch_assoc()) {
                array_push($topics, $topic['Topic']);
            }

            array_push($questions, $myObj);
        }
        $finalObj = array();
        $finalObj["questions"] = $questions;
        $myJSON = json_encode($finalObj);
        echo $myJSON;
    } else {
        echo "no results";
    }
}

function release($json_obj, $conn)
{
    $sql = "UPDATE EXAM
			SET isReleased = 1";
    $conn->query($sql);
    $sql = "SELECT * FROM STUDENT_SCORES
            GROUP BY Username";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $finalObj = array();

        $key = "";
        $questions = array();
        $points = [];
        $answers = "";
        $score = -1;
        $comments = "";

        $first = true;

        while ($row = $result->fetch_assoc()) {
            if($row["Username"] !== $key and $first === false){
                $student = array("questions"=>$questions, "points"=>$points, "answers"=>$answers, "score"=>$score, "comments"=>$comments);
                $finalObj[$key] = $student;
                $key = $row["Username"];
            }else{
                if($first){
                    $key = $row["Username"];
                    $first = false;
                }
                array_push($points, $row["Score"]);
                $answers .= $row["Answer"];
                $score += $row["Score"];
                $comments += $row["Comments"];

                //Hunt down the question
                $sql = "SELECT * FROM QUESTIONS WHERE Name = '" . $row["QID"] . "';";
                $questionsQuery = $conn->query($sql);
                if($questionsQuery->num_rows > 0){
                    $question = array();
                    $questionRow = $questionsQuery->fetch_assoc();
                    $question["func_name"] = $questionRow["Name"];
                    $question["arg_names"] = to_list($questionRow["Arguments"]);
                    $question["description"] = $questionRow["Description"];
                    $question["difficulty"] = $questionRow["Difficulty"];

                    $puts = $conn->query(
                        "SELECT * 
    		            FROM PUTS
    		            WHERE QID = \"" . $row["QID"] . "\"");
                    $inputs = array();
                    $outputs = array();
                    while ($put = $puts->fetch_assoc()) {
                        array_push($inputs, $put['Input']);
                        array_push($outputs, $put['Output']);
                    }

                    $question["inputs"] = $inputs;
                    $question["expected_outputs"] = $outputs;

                    //topics
                    $topicsQuery = $conn->query(
                        "SELECT * 
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
        }

        $student = array("questions"=>$questions, "points"=>$points, "answers"=>$answers, "score"=>$score, "comments"=>$comments);
        $finalObj[$key] = $student;

        $myJSON = json_encode($finalObj);
        echo $myJSON;
    }else{
        echo "No exams";
    }
}

function review_grade($json_obj, $conn)
{
    $username = $json_obj["Username"];
    $sql = "SELECT * FROM STUDENT_SCORES
            WHERE Username = '" . $username . "';";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $finalObj = array();

        $key = "";
        $questions = array();
        $points = [];
        $answers = "";
        $score = -1;
        $comments = "";

        $first = true;

        while ($row = $result->fetch_assoc()) {
            if ($row["Username"] !== $key and $first === false) {
                $student = array("questions" => $questions, "points" => $points, "answers" => $answers, "score" => $score, "comments" => $comments);
                $finalObj[$key] = $student;
                $key = $row["Username"];
            } else {
                if ($first) {
                    $key = $row["Username"];
                    $first = false;
                }
                array_push($points, $row["Score"]);
                $answers .= $row["Answer"];
                $score += $row["Score"];
                $comments += $row["Comments"];

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
                        "SELECT * 
    		            FROM PUTS
    		            WHERE QID = \"" . $row["QID"] . "\"");
                    $inputs = array();
                    $outputs = array();
                    while ($put = $puts->fetch_assoc()) {
                        array_push($inputs, $put['Input']);
                        array_push($outputs, $put['Output']);
                    }

                    $question["inputs"] = $inputs;
                    $question["expected_outputs"] = $outputs;

                    //topics
                    $topicsQuery = $conn->query(
                        "SELECT * 
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
        }
        $student = array("questions" => $questions, "points" => $points, "answers" => $answers, "score" => $score, "comments" => $comments);
        $finalObj[$key] = $student;

        $myJSON = json_encode($finalObj);
        echo $myJSON;
    }
}

function take_exam($json_obj, $conn)
{
    $sql = "SELECT * FROM EXAM_QUESTIONS";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $finalObj = array();
        $questions = array();
        $points = [];
        while ($row = $result->fetch_assoc()) {
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
                    "SELECT * 
    		            FROM PUTS
    		            WHERE QID = \"" . $row["QID"] . "\"");
                $inputs = array();
                $outputs = array();
                while ($put = $puts->fetch_assoc()) {
                    array_push($inputs, $put['Input']);
                    array_push($outputs, $put['Output']);
                }

                $question["inputs"] = $inputs;
                $question["expected_outputs"] = $outputs;

                //topics
                $topicsQuery = $conn->query(
                    "SELECT * 
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
            if ($row['isReleased' === 1]) {
                echo('true');
                break;
            } else {
                echo('false');
                break;
            }
        }
    }
}

function modify_grade($json_obj, $conn){
    $key = array_keys($json_obj)[0];
    $score = $json_obj[$key]["Score"];
    $sql = "UPDATE STUDENT_SCORES
            SET Score = " . $score  .
            "WHERE Username = '" . $key . "';";

    if ($conn->query($sql) === TRUE) {
        echo "success";
    } else {
        echo "fail";
    }

}

?>