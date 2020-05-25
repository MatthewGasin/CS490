# CS490 Project

## What is it?

This is the backend for a Computer Science test creation and distribution site. The project can be found at *web.njit.edu/~jen25*. The username/password for a sample teacher is `2 2` and sample student is `1 1`. 

## How does it work?

The projects middle end sends a POST request with the type of query that must be performed and the corresponding JSON data. From there the backend connects to the NJIT SQL server and performs the query. The SQL database stores instructor and student login info, as well as questions, exams, and student scores. 
