How to build your own Nette Framework
-------------------------------------

1) make sure that you have Java installed (if not, go to http://www.oracle.com/technetwork/java/javase/downloads and
   download "Java Runtime Environment (JRE)")

2) make sure that you have GIT installed (if not, go to http://git-scm.com/download)
   and insert correct path to "$project->gitExecutable" in "build.php"

3) In the main directory of the distribution (the one that this file is in), type
   the following to make all versions of Nette Framework:

   make 
   
   options:
		-f <file>   build file (build.php by default)
		-t <target> target (main by default)
		-a <arg>    argument

   To create a 0.9.x distribution use:

   make -a v0.9.x -a 0.9.7 
