<?php
// Load the OpenAI API client
require 'vendor/autoload.php';

// Set your OpenAI API credentials
$openaiApiKey = 'API_KEY_HERE';

// Set up the OpenAI client
$openai = OpenAI::client($openaiApiKey);

//debug function to print to console
function debug_to_console($data) {
    $output = $data;
    if (is_array($output))
        $output = implode(',', $output);

    echo "<script>console.log('Debug Objects: " . $output . "' );</script>";
}

// This function returns the prompt that describes the season and setting
function getSeason($season) {
    $seed = rand(1,20);
    $fileName = $season . '_' . $seed . ".txt";
    $seasonFileName = 'prompts/season_descriptions/' . $fileName;
    $seasonFile = fopen($seasonFileName, "r") or die("Unable to open file!");
    $seasonPrompt = fread($seasonFile, filesize($seasonFileName));
    fclose($seasonFile);

    return $seasonPrompt;
}

// This function gets 3 random shots from the shot list (Don't need this for now)
function getShotsFromType($type){
    $fileName = 'prompts/photoshoot_types/' . $type;
    // Open the file
    $file = fopen($fileName, 'r'); 

    // Add each line to an array
    if ($file) {
        $array = explode("\n", fread($file, filesize($fileName)));
        $shotList = array_rand($array,3);
    }

    return $shotList;
}

// Gets three extra prompts (not currently used)
function getExtraPrompts($userInput) {
    global $openai;
    $response = $openai->chat()->create([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => 'Give a shot list of bullet points related to the given description. Do not give any text aside from the bullet points.'],
            ['role' => 'user', 'content' => 'Give me a shot list of 3 bullet points related to: ' . $userInput],
        ]
    ]);

    $generatedShotList = $response['choices'][0]['message']['content'];
    echo "THIS IS BEFORE EXPLODE: ";
    echo $generatedShotList;
    $shotListArray = explode(' - ', $generatedShotList);
    //echo $shotListArray[0];

    return $shotListArray;
}

// Function to generate a response based on user input
function generateResponse($userInput, $season, $location, $type) {
    // Midjourney definitions
    $midjourneyDefinition = array(
        'We are going to create Images with a Diffusion model. The following are information to better understand the model and how to interact with it',
        'This is how Midjourney works:
        Midjourney is another AI-powered tool that generates images from user prompts. MidJourney is proficient at adapting actual art styles to create an
        image of any combination of things the user wants. It excels at creating environments, especially fantasy and sci-fi scenes, with dramatic lighting
        that looks like rendered concept art from a video game. How does Midjourney work?
        Midjourney is an AI image generation tool that takes inputs through text prompts and parameters and uses a Machine Learning (ML) algorithm
        trained on a large amount of image data to produce unique images. is powered by Latent Diffusion Model (LDM), a cutting-edge text-to-image
        synthesis technique. Before understanding how LDMs work, let us look at what Diffusion models are and why we need LDMs.
        Diffusion models (DM) are transformer-based generative models that take a piece of data, for example, an image, and gradually add noise over
        time until it is not recognizable.
        Training a diffusion model on such a representation makes it possible to achieve an optimal point between complexity reduction and detail
        preservation, significantly improving visual fidelity. Introducing a cross-attention layer to the model architecture turns the diffusion model into a
        powerful and flexible generator for generally conditioned inputs such as text and bounding boxes, enabling high-resolution convolution-based
        synthesis.',
        'Midjourney model has very high coherency, quality, and it excels at interpreting natural language prompts, is higher resolution, and supports advanced features like
        repeating patterns. Some features are it has:
        - Much wider stylistic range and more responsive to prompting
        - Much higher image quality (2x resolution increase) improved dynamic range
        - More detailed images. Details more likely to be correct. Less unwanted text.',
    );
    $seasonInfo = getSeason($season);
    // Call the OpenAI API to get a response
    global $openai;
    $response = $openai->chat()->create([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => 'Given some information about the photograph provide one detailed description of a photograph for Midjourney to generate related to the given topic.'],
            ['role' => 'user', 'content' => $midjourneyDefinition[0]],
            ['role' => 'user', 'content' => $midjourneyDefinition[1]],
            ['role' => 'user', 'content' => $midjourneyDefinition[2]],
            ['role' => 'user', 'content' => 'The season of the photo must be: ' . $season],
            ['role' => 'user', 'content' => 'The responses must be limited to 700 characters always.'],
            ['role' => 'user', 'content' => 'The location of the photo is: ' . $location],
            ['role' => 'user', 'content' => 'The type of photo shoot is: ' . $type],
            ['role' => 'user', 'content' => 'Topic: ' . $userInput],
            ['role' => 'user', 'content' => 'Description of photo: '],
        ]
    ]);

    // Extract the generated text from the API response
    $generatedText = $response['choices'][0]['message']['content'];

    // Combine the chatgpt prompt with the stored setting/season prompt
    $combinationResponse = $openai->chat()->create([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => 'Combine 2 descriptions to provide one cohesive and detailed physical description of a photograph related to the given topic to be generated by Midjourney. Responses are limited to 1150 characters.',],
            ['role' => 'user', 'content' => 'The responses must be limited to 1300 characters always.'],
            ['role' => 'user', 'content' => $midjourneyDefinition[0]],
            ['role' => 'user', 'content' => $midjourneyDefinition[1]],
            ['role' => 'user', 'content' => $midjourneyDefinition[2]],
            ['role' => 'user', 'content' => 'The second description is more heavily weighted for the environment and setting.'],
            ['role' => 'user', 'content' => "Description 1: " . $generatedText],
            ['role' => 'user', 'content' => "Description 2: " . $seasonInfo],
            ['role' => 'user', 'content' => 'Combined concise description in less than 1150 characters: '],
        ]
    ]);
    $cameraInfo = ' The photograph is expertly taken with a Nikon D5100 camera, using an aperture of f/2.8,
                 ISO 800, and a shutter speed of 1/100 sec. It is rendered in UHD dtm HDR 8k format, with an 
                aspect ratio of 2:3, --v 5.2 --turbo --style raw';

    // Extract the combined description from the API response
    $combinedText = $combinationResponse['choices'][0]['message']['content'];
    // Add camera settings for midjourney to process at the end of the description
    $combinedText .= $cameraInfo;

    // Return the combined response
    return $combinedText;
}

// Example usage: Get user input and generate a response
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the user input from the form submission
    $userInput = $_POST['user_input'];
    $location = $_POST['location_input'];
    
    // Get the dropdown inputs
    $type = $_POST['type_input'];
    $season = $_POST['season_input'];
    //$setting = $_POST['setting_input'];

    // Get extra prompts
    //$shotList = getExtraPrompts($userInput);

    // foreach ($shotList as $shot) {
    //     echo '<br><br> The shot input is: <br>';
    //     echo $shot;

    //     // Generate a response based on user input
    //     $generatedResponse = generateResponse($shot, $season, $setting, $location, $type);

    //     // Output the generated response
    //     echo '<br><br> The chatgpt output is: <br>';
    //     echo $generatedResponse;
    //   }

    $generatedResponse = generateResponse($userInput, $season, $location, $type);
    echo '<br><br> The chatgpt output is: <br>';
    echo $generatedResponse;

}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Chatbot Integration with GPT-3</title>
</head>
<body>
    <form method="POST" action="">
        <label for="user_input">Description:</label><br>
        <textarea id="user_input" name="user_input" rows="4" cols="50"></textarea>
        <br><br>
        <label for="type_input">Choose a season:</label>
        <select name="type_input" id="type_input">
            <option value="Wedding">Wedding</option>
            <option value="Engagement">Engagement</option>
            <option value="Theme">Theme</option>
            <option value="Landscape">Landscape</option>
        </select>
        <br><br>
        <label for="location_input">Location:</label><br>
        <textarea id="location_input" name="location_input" rows="1" cols="20"></textarea>
        <br><br>
        <label for="season">Choose a season:</label>
        <select name="season_input" id="season_input">
            <option value="spring">Spring</option>
            <option value="summer">Summer</option>
            <option value="fall">Fall</option>
            <option value="winter">Winter</option>
        </select>
        <br><br>
        <label for="setting_input">Choose a setting:</label>
        <select name="setting_input" id="setting_input">
            <option value="indoor">Indoor</option>
            <option value="outdoor">Outdoor</option>
        </select>
        <br><br>
        <input type="submit" value="Get Response">
    </form>
</body>
</html>